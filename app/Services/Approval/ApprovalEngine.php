<?php

namespace App\Services\Approval;

use App\Enums\ApprovalActionType;
use App\Enums\ApprovalInstanceStatus;
use App\Enums\ApprovalStepStatus;
use App\Models\ApprovalAction;
use App\Models\ApprovalDefinition;
use App\Models\ApprovalDelegation;
use App\Models\ApprovalInstance;
use App\Models\ApprovalInstanceStep;
use App\Models\ApprovalStep;
use App\Models\Employee;
use App\Models\EmploymentHistory;
use App\Models\LegalEntity;
use App\Models\User;
use App\Services\Organization\LegalEntityScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ApprovalEngine
{
    public function __construct(private readonly LegalEntityScope $scope) {}

    public function createDefinition(
        User $actor,
        int $legalEntityId,
        string $key,
        string $subjectType,
        string $effectiveFrom,
        bool $includePayrollStep = false,
        int $reminderAfterHours = 24,
        int $escalationAfterHours = 72,
    ): ApprovalDefinition {
        return DB::transaction(function () use ($actor, $legalEntityId, $key, $subjectType, $effectiveFrom, $includePayrollStep, $reminderAfterHours, $escalationAfterHours): ApprovalDefinition {
            $existing = ApprovalDefinition::query()
                ->where('legal_entity_id', $legalEntityId)->where('key', $key)
                ->where('status', 'active')->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $definition = ApprovalDefinition::query()->create([
                'legal_entity_id' => $legalEntityId,
                'key' => $key,
                'subject_type' => $subjectType,
                'version' => 1,
                'risk_class' => 'confidential',
                'effective_from' => $effectiveFrom,
                'status' => 'active',
                'reminder_after_hours' => $reminderAfterHours,
                'escalation_after_hours' => $escalationAfterHours,
                'created_by' => $actor->getKey(),
            ]);

            $steps = [
                ['step_order' => 1, 'name' => 'Direct manager', 'resolver_type' => 'direct_manager', 'required_permission' => 'leave.approve-manager'],
                ['step_order' => 2, 'name' => 'HR validation', 'resolver_type' => 'scoped_permission', 'required_permission' => 'leave.review'],
            ];
            if ($includePayrollStep) {
                $steps[] = ['step_order' => 3, 'name' => 'Payroll confirmation', 'resolver_type' => 'scoped_permission', 'required_permission' => 'leave.confirm-payroll'];
            }
            foreach ($steps as $step) {
                $definition->steps()->create($step + ['minimum_approvals' => 1, 'due_after_hours' => $reminderAfterHours]);
            }

            return $definition->load('steps');
        });
    }

    public function definitionFor(int $legalEntityId, string $key, string $subjectType, string $date): ApprovalDefinition
    {
        return ApprovalDefinition::query()
            ->where('legal_entity_id', $legalEntityId)
            ->where('key', $key)
            ->where('subject_type', $subjectType)
            ->effectiveOn($date)
            ->with('steps')
            ->latest('version')
            ->firstOrFail();
    }

    /** @param array<string, mixed> $snapshot */
    public function start(
        User $requester,
        Employee $employee,
        string $subjectType,
        string $subjectPublicId,
        array $snapshot,
        ApprovalDefinition $definition,
    ): ApprovalInstance {
        $encoded = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $correlationId = (string) Str::ulid();

        $instance = ApprovalInstance::query()->create([
            'legal_entity_id' => $employee->legal_entity_id,
            'approval_definition_id' => $definition->getKey(),
            'subject_type' => $subjectType,
            'subject_public_id' => $subjectPublicId,
            'subject_snapshot' => $snapshot,
            'snapshot_checksum' => hash('sha256', $encoded),
            'requested_by' => $requester->getKey(),
            'status' => ApprovalInstanceStatus::Pending->value,
            'current_step_order' => 1,
            'correlation_id' => $correlationId,
            'submitted_at' => now(),
        ]);

        foreach ($definition->steps as $definitionStep) {
            $this->snapshotStep($instance, $definitionStep, $employee, $definitionStep->step_order === 1);
        }

        ApprovalAction::query()->create([
            'approval_instance_id' => $instance->getKey(),
            'actor_user_id' => $requester->getKey(),
            'action' => ApprovalActionType::Submit->value,
            'idempotency_hash' => hash('sha256', $correlationId.'|submit'),
            'acted_at' => now(),
        ]);

        return $instance->load('steps');
    }

    /** @param array{decision: string, review_notes: string, idempotency_key?: string} $data */
    public function act(User $actor, ApprovalInstance $instance, array $data): ApprovalInstance
    {
        return DB::transaction(function () use ($actor, $instance, $data): ApprovalInstance {
            $locked = ApprovalInstance::query()->whereKey($instance->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->instanceStatus() !== ApprovalInstanceStatus::Pending || $locked->current_step_order === null) {
                throw ValidationException::withMessages(['decision' => __('leave.validation.already_reviewed')]);
            }
            if ((int) $locked->requested_by === (int) $actor->getKey()) {
                abort(403);
            }

            $step = ApprovalInstanceStep::query()
                ->where('approval_instance_id', $locked->getKey())
                ->where('step_order', $locked->current_step_order)
                ->lockForUpdate()->firstOrFail();
            $this->authorizeStep($actor, $locked, $step);

            $decision = (string) $data['decision'];
            $action = match ($decision) {
                'approve' => ApprovalActionType::Approve,
                'reject' => ApprovalActionType::Reject,
                'request_revision' => ApprovalActionType::RequestRevision,
                default => throw ValidationException::withMessages(['decision' => __('leave.validation.invalid_decision')]),
            };
            $idempotencyKey = trim((string) ($data['idempotency_key'] ?? Str::ulid()));
            $idempotencyHash = hash('sha256', $locked->public_id.'|'.$step->public_id.'|'.$actor->getKey().'|'.$idempotencyKey);
            if (ApprovalAction::query()->where('idempotency_hash', $idempotencyHash)->exists()) {
                return $locked->refresh()->load('steps');
            }

            ApprovalAction::query()->create([
                'approval_instance_id' => $locked->getKey(),
                'approval_instance_step_id' => $step->getKey(),
                'actor_user_id' => $actor->getKey(),
                'acting_for_user_id' => $step->delegated_from_user_id,
                'action' => $action->value,
                'note' => trim((string) $data['review_notes']),
                'idempotency_hash' => $idempotencyHash,
                'acted_at' => now(),
            ]);

            if ($action === ApprovalActionType::Approve) {
                $step->update(['status' => ApprovalStepStatus::Approved->value, 'completed_at' => now()]);
                $next = ApprovalInstanceStep::query()
                    ->where('approval_instance_id', $locked->getKey())
                    ->where('step_order', '>', $step->step_order)
                    ->orderBy('step_order')->lockForUpdate()->first();
                if ($next) {
                    $next->update(['status' => ApprovalStepStatus::Pending->value]);
                    $locked->update(['current_step_order' => $next->step_order]);
                } else {
                    $locked->update([
                        'status' => ApprovalInstanceStatus::Approved->value,
                        'current_step_order' => null,
                        'completed_at' => now(),
                    ]);
                }
            } elseif ($action === ApprovalActionType::Reject) {
                $step->update(['status' => ApprovalStepStatus::Rejected->value, 'completed_at' => now()]);
                $locked->update([
                    'status' => ApprovalInstanceStatus::Rejected->value,
                    'current_step_order' => null,
                    'completed_at' => now(),
                ]);
                $this->cancelRemainingSteps($locked);
            } else {
                $step->update(['status' => ApprovalStepStatus::RevisionRequested->value, 'completed_at' => now()]);
                $locked->update([
                    'status' => ApprovalInstanceStatus::RevisionRequested->value,
                    'current_step_order' => null,
                    'completed_at' => now(),
                ]);
                $this->cancelRemainingSteps($locked);
            }

            return $locked->refresh()->load('steps');
        });
    }

    public function cancel(User $actor, ApprovalInstance $instance): ApprovalInstance
    {
        return DB::transaction(function () use ($actor, $instance): ApprovalInstance {
            $locked = ApprovalInstance::query()->whereKey($instance->getKey())->lockForUpdate()->firstOrFail();
            if ((int) $locked->requested_by !== (int) $actor->getKey()) {
                abort(403);
            }
            if ($locked->instanceStatus() !== ApprovalInstanceStatus::Pending) {
                throw ValidationException::withMessages(['request' => __('leave.validation.already_reviewed')]);
            }
            $locked->update([
                'status' => ApprovalInstanceStatus::Cancelled->value,
                'current_step_order' => null,
                'cancelled_at' => now(),
            ]);
            $this->cancelRemainingSteps($locked);
            ApprovalAction::query()->create([
                'approval_instance_id' => $locked->getKey(),
                'actor_user_id' => $actor->getKey(),
                'action' => ApprovalActionType::Cancel->value,
                'idempotency_hash' => hash('sha256', $locked->public_id.'|cancel'),
                'acted_at' => now(),
            ]);

            return $locked->refresh()->load('steps');
        });
    }

    /** @param array{effective_from: string, effective_to: string, reason: string} $data */
    public function createDelegation(
        User $actor,
        LegalEntity $entity,
        User $delegator,
        User $delegate,
        array $data,
    ): ApprovalDelegation {
        if ($delegator->is($delegate) || ! $delegate->can('leave.approve-manager')) {
            throw ValidationException::withMessages(['delegate_user_id' => __('leave.validation.invalid_delegate')]);
        }

        return DB::transaction(function () use ($actor, $entity, $delegator, $delegate, $data): ApprovalDelegation {
            $overlap = ApprovalDelegation::query()
                ->where('legal_entity_id', $entity->getKey())
                ->where('delegator_user_id', $delegator->getKey())
                ->where('status', 'active')
                ->whereDate('effective_from', '<=', $data['effective_to'])
                ->whereDate('effective_to', '>=', $data['effective_from'])
                ->lockForUpdate()->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['effective_from' => __('leave.validation.delegation_overlap')]);
            }

            $delegation = ApprovalDelegation::query()->create([
                'legal_entity_id' => $entity->getKey(),
                'delegator_user_id' => $delegator->getKey(),
                'delegate_user_id' => $delegate->getKey(),
                'subject_type' => 'leave_request',
                'effective_from' => $data['effective_from'],
                'effective_to' => $data['effective_to'],
                'reason' => trim($data['reason']),
                'status' => 'active',
                'granted_by' => $actor->getKey(),
            ]);
            activity('approval')->causedBy($actor)->performedOn($delegation)->event('approval_delegation_created')
                ->withProperties([
                    'legal_entity_public_id' => $entity->public_id,
                    'delegator_user_id' => $delegator->getKey(),
                    'delegate_user_id' => $delegate->getKey(),
                    'subject_type' => 'leave_request',
                    'effective_from' => $data['effective_from'],
                    'effective_to' => $data['effective_to'],
                ])->log('Temporary leave approval delegation created.');

            return $delegation;
        });
    }

    public function revokeDelegation(User $actor, ApprovalDelegation $delegation): ApprovalDelegation
    {
        return DB::transaction(function () use ($actor, $delegation): ApprovalDelegation {
            $locked = ApprovalDelegation::query()->whereKey($delegation->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'active') {
                return $locked;
            }
            $locked->update(['status' => 'revoked', 'revoked_by' => $actor->getKey(), 'revoked_at' => now()]);
            activity('approval')->causedBy($actor)->performedOn($locked)->event('approval_delegation_revoked')
                ->withProperties(['delegation_public_id' => $locked->public_id])->log('Approval delegation revoked.');

            return $locked->refresh();
        });
    }

    /** @return Collection<int, User> */
    public function recipientsForCurrentStep(ApprovalInstance $instance): Collection
    {
        $step = ApprovalInstanceStep::query()
            ->where('approval_instance_id', $instance->getKey())
            ->where('step_order', $instance->current_step_order)->first();
        if (! $step) {
            return new Collection;
        }
        if ($step->assigned_approver_user_id) {
            return User::query()->whereKey($step->assigned_approver_user_id)->where('is_active', true)->get();
        }
        if (! $step->required_permission) {
            return new Collection;
        }

        $today = now()->toDateString();

        return User::query()->where('is_active', true)
            ->whereHas('roles.permissions', fn (Builder $query) => $query->where('name', $step->required_permission))
            ->whereHas('legalEntityAccess', fn (Builder $query) => $query
                ->where('legal_entity_id', $instance->legal_entity_id)->where('access_level', 'manage')
                ->whereDate('effective_from', '<=', $today)
                ->where(fn (Builder $period) => $period->whereNull('effective_to')->orWhereDate('effective_to', '>', $today)))
            ->get();
    }

    private function snapshotStep(ApprovalInstance $instance, ApprovalStep $definitionStep, Employee $employee, bool $isFirst): void
    {
        $assigned = null;
        $delegatedFrom = null;
        $snapshot = ['resolver_type' => $definitionStep->resolver_type, 'resolved_at' => now()->toIso8601String()];
        if ($definitionStep->resolver_type === 'direct_manager') {
            [$assigned, $delegatedFrom, $strategy] = $this->resolveManagerApprover($employee);
            $snapshot['strategy'] = $strategy;
            $snapshot['assigned_user_id'] = $assigned->getKey();
        } else {
            $snapshot['required_permission'] = $definitionStep->required_permission;
            if ($this->scopedUsers((int) $employee->legal_entity_id, (string) $definitionStep->required_permission)->isEmpty()) {
                throw ValidationException::withMessages(['leave_type_public_id' => __('leave.validation.missing_approver')]);
            }
        }

        ApprovalInstanceStep::query()->create([
            'approval_instance_id' => $instance->getKey(),
            'step_order' => $definitionStep->step_order,
            'name' => $definitionStep->name,
            'resolver_type' => $definitionStep->resolver_type,
            'resolver_snapshot' => $snapshot,
            'assigned_approver_user_id' => $assigned?->getKey(),
            'delegated_from_user_id' => $delegatedFrom?->getKey(),
            'required_permission' => $definitionStep->required_permission,
            'status' => $isFirst ? ApprovalStepStatus::Pending->value : ApprovalStepStatus::Waiting->value,
            'due_at' => now()->addHours((int) $definitionStep->due_after_hours),
        ]);
    }

    /** @return array{User, ?User, string} */
    private function resolveManagerApprover(Employee $employee): array
    {
        $today = now()->toDateString();
        $history = EmploymentHistory::query()->where('employee_id', $employee->getKey())->effectiveOn($today)->first();
        $manager = $history?->manager;
        $managerUser = $manager?->user()->where('is_active', true)->first();
        if ($managerUser instanceof User) {
            $delegation = ApprovalDelegation::query()
                ->where('legal_entity_id', $employee->legal_entity_id)
                ->where('delegator_user_id', $managerUser->getKey())
                ->where(fn (Builder $query) => $query->whereNull('subject_type')->orWhere('subject_type', 'leave_request'))
                ->effectiveOn($today)->with('delegate')->first();
            if ($delegation?->delegate instanceof User && $delegation->delegate->is_active
                && $delegation->delegate->can('leave.approve-manager')) {
                return [$delegation->delegate, $managerUser, 'active_delegation'];
            }
            if ($managerUser->can('leave.approve-manager')) {
                return [$managerUser, null, 'direct_manager'];
            }

            $upperHistory = $manager->currentEmployment()->first();
            $upperManager = $upperHistory?->manager;
            $upperUser = $upperManager instanceof Employee
                ? $upperManager->user()->where('is_active', true)->first()
                : null;
            if ($upperUser instanceof User && $upperUser->can('leave.approve-manager')) {
                return [$upperUser, $managerUser, 'upper_manager'];
            }
        }

        $fallback = $this->scopedUsers((int) $employee->legal_entity_id, 'leave.review')->first();
        if (! $fallback instanceof User) {
            throw ValidationException::withMessages(['leave_type_public_id' => __('leave.validation.missing_approver')]);
        }

        return [$fallback, $managerUser instanceof User ? $managerUser : null, 'scoped_hr_fallback'];
    }

    /** @return Collection<int, User> */
    private function scopedUsers(int $legalEntityId, string $permission): Collection
    {
        $today = now()->toDateString();

        return User::query()->where('is_active', true)
            ->whereHas('roles.permissions', fn (Builder $query) => $query->where('name', $permission))
            ->whereHas('legalEntityAccess', fn (Builder $query) => $query
                ->where('legal_entity_id', $legalEntityId)->where('access_level', 'manage')
                ->whereDate('effective_from', '<=', $today)
                ->where(fn (Builder $period) => $period->whereNull('effective_to')->orWhereDate('effective_to', '>', $today)))
            ->get();
    }

    private function authorizeStep(User $actor, ApprovalInstance $instance, ApprovalInstanceStep $step): void
    {
        if ($step->assigned_approver_user_id) {
            abort_unless((int) $step->assigned_approver_user_id === (int) $actor->getKey(), 403);

            return;
        }

        abort_unless(
            is_string($step->required_permission)
            && $actor->can($step->required_permission)
            && $this->scope->manages($actor, (int) $instance->legal_entity_id),
            403,
        );
    }

    private function cancelRemainingSteps(ApprovalInstance $instance): void
    {
        ApprovalInstanceStep::query()->where('approval_instance_id', $instance->getKey())
            ->whereIn('status', [ApprovalStepStatus::Waiting->value, ApprovalStepStatus::Pending->value])
            ->update(['status' => ApprovalStepStatus::Cancelled->value, 'completed_at' => now()]);
    }
}
