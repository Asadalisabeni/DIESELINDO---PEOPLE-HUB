<?php

namespace App\Services\Leave;

use App\Enums\ApprovalInstanceStatus;
use App\Enums\LeaveLedgerEntryType;
use App\Enums\LeaveRequestStatus;
use App\Models\ApprovalInstance;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeeScheduleAssignment;
use App\Models\EmploymentHistory;
use App\Models\Holiday;
use App\Models\LeaveEntitlement;
use App\Models\LeaveLedgerEntry;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\LegalEntity;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Notifications\LeaveApprovalPending;
use App\Notifications\LeaveBalanceExpiring;
use App\Notifications\LeaveRequestReviewed;
use App\Services\Approval\ApprovalEngine;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LeaveManager
{
    public function __construct(private readonly ApprovalEngine $approvals) {}

    /** @param array<string, mixed> $data */
    public function createType(User $actor, LegalEntity $entity, array $data): LeaveType
    {
        return DB::transaction(function () use ($actor, $entity, $data): LeaveType {
            if (LeaveType::query()->where('legal_entity_id', $entity->getKey())
                ->where('code', strtoupper((string) $data['code']))->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['code' => __('leave.validation.duplicate_type')]);
            }

            $type = LeaveType::query()->create([
                'legal_entity_id' => $entity->getKey(),
                'code' => strtoupper(trim((string) $data['code'])),
                'name' => trim((string) $data['name']),
                'category' => (string) $data['category'],
                'is_paid' => (bool) $data['is_paid'],
                'requires_balance' => (bool) $data['requires_balance'],
                'unit' => 'day',
                'evidence_required_from_days' => $this->nullableInteger($data['evidence_required_from_days'] ?? null),
                'requires_payroll_confirmation' => (bool) $data['requires_payroll_confirmation'],
                'status' => 'active',
                'created_by' => $actor->getKey(),
            ]);
            $this->createPolicy($actor, $type, $data);
            $this->approvals->createDefinition(
                $actor,
                (int) $type->legal_entity_id,
                'leave.'.strtolower($type->code),
                'leave_request',
                (string) $data['effective_from'],
                (bool) $type->requires_payroll_confirmation,
                (int) $data['approval_reminder_hours'],
                (int) $data['approval_escalation_hours'],
            );

            activity('leave')->causedBy($actor)->performedOn($type)->event('leave_type_created')
                ->withProperties(['legal_entity_public_id' => $entity->public_id, 'leave_type_public_id' => $type->public_id])
                ->log('Effective-dated leave type and initial policy created.');

            return $type->load('policies');
        });
    }

    /** @param array<string, mixed> $data */
    public function createPolicy(User $actor, LeaveType $type, array $data): LeavePolicy
    {
        return DB::transaction(function () use ($actor, $type, $data): LeavePolicy {
            $from = (string) $data['effective_from'];
            $to = $this->nullableString($data['effective_to'] ?? null);
            $overlap = LeavePolicy::query()->where('leave_type_id', $type->getKey())
                ->where('status', 'active')
                ->whereDate('effective_from', '<=', $to ?? '9999-12-31')
                ->where(fn (Builder $query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>', $from))
                ->lockForUpdate()->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['effective_from' => __('leave.validation.policy_overlap')]);
            }

            $version = ((int) LeavePolicy::query()->where('leave_type_id', $type->getKey())->lockForUpdate()->max('version')) + 1;

            $policy = LeavePolicy::query()->create([
                'legal_entity_id' => $type->legal_entity_id,
                'leave_type_id' => $type->getKey(),
                'version' => $version,
                'eligibility_months' => (int) $data['eligibility_months'],
                'entitlement_quantity' => $this->decimal((string) $data['entitlement_quantity']),
                'validity_months' => $this->nullableInteger($data['validity_months'] ?? null),
                'carry_forward_enabled' => (bool) $data['carry_forward_enabled'],
                'carry_forward_limit' => $this->nullableDecimal($data['carry_forward_limit'] ?? null),
                'minimum_notice_days' => (int) $data['minimum_notice_days'],
                'maximum_request_days' => $this->nullableDecimal($data['maximum_request_days'] ?? null),
                'effective_from' => $from,
                'effective_to' => $to,
                'status' => 'active',
                'created_by' => $actor->getKey(),
                'approved_by' => $actor->getKey(),
                'approved_at' => now(),
            ]);

            activity('leave')->causedBy($actor)->performedOn($policy)->event('leave_policy_created')
                ->withProperties([
                    'leave_type_public_id' => $type->public_id,
                    'policy_public_id' => $policy->public_id,
                    'version' => $policy->version,
                    'effective_from' => $from,
                    'effective_to' => $to,
                ])->log('Effective-dated leave policy version created.');

            return $policy;
        });
    }

    /** @param array<string, mixed> $data */
    public function grantEntitlement(User $actor, Employee $employee, LeaveType $type, array $data): LeaveEntitlement
    {
        abort_unless((int) $employee->legal_entity_id === (int) $type->legal_entity_id, 404);

        return DB::transaction(function () use ($actor, $employee, $type, $data): LeaveEntitlement {
            Employee::query()->whereKey($employee->getKey())->lockForUpdate()->firstOrFail();
            $reference = strtoupper(trim((string) $data['grant_reference']));
            $existing = LeaveEntitlement::query()->where('legal_entity_id', $employee->legal_entity_id)
                ->where('grant_reference', $reference)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $validFrom = (string) $data['valid_from'];
            $policy = LeavePolicy::query()->where('leave_type_id', $type->getKey())->effectiveOn($validFrom)
                ->latest('version')->firstOrFail();
            $quantity = $this->decimal((string) $data['quantity']);
            if ($this->toHundredths($quantity) <= 0) {
                throw ValidationException::withMessages(['quantity' => __('leave.validation.positive_quantity')]);
            }
            $validTo = $this->nullableString($data['valid_to'] ?? null);
            if (! $validTo && $policy->validity_months) {
                $validTo = CarbonImmutable::parse($validFrom)->addMonths((int) $policy->validity_months)->subDay()->toDateString();
            }

            $entitlement = LeaveEntitlement::query()->create([
                'legal_entity_id' => $employee->legal_entity_id,
                'employee_id' => $employee->getKey(),
                'leave_type_id' => $type->getKey(),
                'leave_policy_id' => $policy->getKey(),
                'grant_reference' => $reference,
                'valid_from' => $validFrom,
                'valid_to' => $validTo,
                'opening_quantity' => $quantity,
                'source' => (string) $data['source'],
                'status' => 'active',
                'created_by' => $actor->getKey(),
            ]);
            LeaveLedgerEntry::query()->create([
                'legal_entity_id' => $employee->legal_entity_id,
                'leave_entitlement_id' => $entitlement->getKey(),
                'employee_id' => $employee->getKey(),
                'entry_type' => $data['source'] === 'opening' ? LeaveLedgerEntryType::Opening->value : LeaveLedgerEntryType::Entitlement->value,
                'quantity' => $quantity,
                'effective_date' => $validFrom,
                'reference_key' => 'GRANT:'.$reference,
                'reason' => trim((string) $data['reason']),
                'created_by' => $actor->getKey(),
            ]);

            activity('leave')->causedBy($actor)->performedOn($employee)->event('leave_entitlement_granted')
                ->withProperties([
                    'legal_entity_public_id' => $employee->legalEntity()->value('public_id'),
                    'employee_public_id' => $employee->public_id,
                    'entitlement_public_id' => $entitlement->public_id,
                    'leave_type_public_id' => $type->public_id,
                    'quantity' => $quantity,
                    'valid_from' => $validFrom,
                    'valid_to' => $validTo,
                ])->log('Leave entitlement granted through append-only ledger.');

            return $entitlement->load('ledgerEntries');
        });
    }

    /** @param array<string, mixed> $data */
    public function adjust(User $actor, LeaveEntitlement $entitlement, array $data): LeaveLedgerEntry
    {
        return DB::transaction(function () use ($actor, $entitlement, $data): LeaveLedgerEntry {
            $locked = LeaveEntitlement::query()->whereKey($entitlement->getKey())->lockForUpdate()->firstOrFail();
            $quantity = $this->decimal((string) $data['quantity']);
            if ($this->toHundredths($quantity) === 0) {
                throw ValidationException::withMessages(['quantity' => __('leave.validation.non_zero_adjustment')]);
            }
            $reference = strtoupper(trim((string) ($data['reference_key'] ?? 'ADJ-'.Str::ulid())));
            $existing = LeaveLedgerEntry::query()->where('leave_entitlement_id', $locked->getKey())
                ->where('reference_key', $reference)->first();
            if ($existing) {
                return $existing;
            }

            $entry = LeaveLedgerEntry::query()->create([
                'legal_entity_id' => $locked->legal_entity_id,
                'leave_entitlement_id' => $locked->getKey(),
                'employee_id' => $locked->employee_id,
                'entry_type' => LeaveLedgerEntryType::Adjustment->value,
                'quantity' => $quantity,
                'effective_date' => (string) $data['effective_date'],
                'reference_key' => $reference,
                'reason' => trim((string) $data['reason']),
                'created_by' => $actor->getKey(),
            ]);

            activity('leave')->causedBy($actor)->performedOn($locked)->event('leave_balance_adjusted')
                ->withProperties(['entitlement_public_id' => $locked->public_id, 'quantity' => $quantity, 'reference' => $reference])
                ->log('Leave balance adjusted by an immutable ledger entry.');

            return $entry;
        });
    }

    /** @param array<string, mixed> $data */
    public function submit(User $actor, Employee $employee, LeaveType $type, array $data, ?int $evidenceDocumentId = null): LeaveRequest
    {
        abort_unless((int) $actor->employee_id === (int) $employee->getKey(), 403);
        abort_unless((int) $type->legal_entity_id === (int) $employee->legal_entity_id && $type->status === 'active', 404);

        return DB::transaction(function () use ($actor, $employee, $type, $data, $evidenceDocumentId): LeaveRequest {
            Employee::query()->whereKey($employee->getKey())->lockForUpdate()->firstOrFail();
            $start = CarbonImmutable::parse((string) $data['start_date']);
            $end = CarbonImmutable::parse((string) $data['end_date']);
            if ($end->lessThan($start)) {
                throw ValidationException::withMessages(['end_date' => __('leave.validation.invalid_period')]);
            }
            $policy = LeavePolicy::query()->where('leave_type_id', $type->getKey())
                ->effectiveOn($start->toDateString())->latest('version')->firstOrFail();
            $this->assertEligible($employee, $policy, $start);
            $totalDays = $this->workingDays($employee, $start, $end);
            if ($totalDays < 1) {
                throw ValidationException::withMessages(['start_date' => __('leave.validation.no_working_days')]);
            }
            if ($policy->maximum_request_days !== null
                && $totalDays > $this->toHundredths((string) $policy->maximum_request_days) / 100) {
                throw ValidationException::withMessages(['end_date' => __('leave.validation.maximum_days')]);
            }
            $notice = CarbonImmutable::today($employee->legalEntity()->value('timezone') ?: config('app.timezone'))
                ->diffInDays($start, false);
            if ($notice < (int) $policy->minimum_notice_days) {
                throw ValidationException::withMessages(['start_date' => __('leave.validation.minimum_notice')]);
            }
            $requiresEvidence = $type->evidence_required_from_days !== null
                && $totalDays >= (int) $type->evidence_required_from_days;
            if ($requiresEvidence && ! $evidenceDocumentId) {
                throw ValidationException::withMessages(['evidence' => __('leave.validation.evidence_required')]);
            }
            if ($evidenceDocumentId && ! EmployeeDocument::query()->whereKey($evidenceDocumentId)
                ->where('employee_id', $employee->getKey())->where('legal_entity_id', $employee->legal_entity_id)->exists()) {
                abort(404);
            }
            if (LeaveRequest::query()->where('employee_id', $employee->getKey())
                ->whereIn('status', [
                    LeaveRequestStatus::PendingManager->value, LeaveRequestStatus::PendingHr->value,
                    LeaveRequestStatus::PendingPayroll->value, LeaveRequestStatus::Approved->value,
                ])->whereDate('start_date', '<=', $end->toDateString())
                ->whereDate('end_date', '>=', $start->toDateString())->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['start_date' => __('leave.validation.overlap')]);
            }
            if ($type->requires_balance) {
                $this->assertAvailableBalance($employee, $type, $end, $totalDays);
            }

            $fingerprint = hash('sha256', implode('|', [
                $employee->public_id, $type->public_id, $start->toDateString(), $end->toDateString(), $totalDays,
            ]));
            $request = LeaveRequest::query()->create([
                'legal_entity_id' => $employee->legal_entity_id,
                'employee_id' => $employee->getKey(),
                'leave_type_id' => $type->getKey(),
                'leave_policy_id' => $policy->getKey(),
                'requested_by' => $actor->getKey(),
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'total_days' => $this->decimal((string) $totalDays),
                'reason' => trim((string) $data['reason']),
                'evidence_document_id' => $evidenceDocumentId,
                'is_paid_snapshot' => $type->is_paid,
                'requires_balance_snapshot' => $type->requires_balance,
                'status' => LeaveRequestStatus::PendingManager->value,
                'request_fingerprint' => $fingerprint,
                'submitted_at' => now(),
            ]);
            $definition = $this->approvals->definitionFor(
                (int) $type->legal_entity_id,
                'leave.'.strtolower($type->code),
                'leave_request',
                $start->toDateString(),
            );
            $instance = $this->approvals->start($actor, $employee, 'leave_request', $request->public_id, [
                'subject_type' => 'leave_request',
                'subject_public_id' => $request->public_id,
                'employee_public_id' => $employee->public_id,
                'leave_type_public_id' => $type->public_id,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'total_days' => $request->total_days,
                'is_paid' => $request->is_paid_snapshot,
            ], $definition);
            $request->update(['approval_instance_id' => $instance->getKey()]);

            Notification::send($this->approvals->recipientsForCurrentStep($instance), new LeaveApprovalPending($request));
            activity('leave')->causedBy($actor)->performedOn($employee)->event('leave_request_submitted')
                ->withProperties([
                    'legal_entity_public_id' => $employee->legalEntity()->value('public_id'),
                    'employee_public_id' => $employee->public_id, 'leave_request_public_id' => $request->public_id,
                    'leave_type_public_id' => $type->public_id, 'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(), 'total_days' => $totalDays, 'has_evidence' => $evidenceDocumentId !== null,
                ])->log('Leave request submitted to generic approval workflow.');

            return $request->load(['type', 'policy', 'approvalInstance.steps']);
        });
    }

    /** @param array{decision: string, review_notes: string, idempotency_key?: string} $data */
    public function review(User $actor, LeaveRequest $request, array $data): LeaveRequest
    {
        return DB::transaction(function () use ($actor, $request, $data): LeaveRequest {
            $locked = LeaveRequest::query()->with(['approvalInstance', 'employee', 'requester', 'type'])
                ->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            $instance = $locked->approvalInstance;
            abort_unless($instance instanceof ApprovalInstance, 409);
            $updatedInstance = $this->approvals->act($actor, $instance, $data);
            $instanceStatus = $updatedInstance->instanceStatus();

            if ($instanceStatus === ApprovalInstanceStatus::Pending) {
                $nextStep = $updatedInstance->steps->firstWhere('step_order', $updatedInstance->current_step_order);
                $nextStatus = match ($nextStep?->required_permission) {
                    'leave.review' => LeaveRequestStatus::PendingHr,
                    'leave.confirm-payroll' => LeaveRequestStatus::PendingPayroll,
                    default => LeaveRequestStatus::PendingManager,
                };
                $locked->update(['status' => $nextStatus->value]);
                Notification::send($this->approvals->recipientsForCurrentStep($updatedInstance), new LeaveApprovalPending($locked));
            } elseif ($instanceStatus === ApprovalInstanceStatus::Approved) {
                if ($locked->requires_balance_snapshot) {
                    $this->postUsage($actor, $locked);
                }
                $locked->update(['status' => LeaveRequestStatus::Approved->value, 'approved_at' => now()]);
            } elseif ($instanceStatus === ApprovalInstanceStatus::Rejected) {
                $locked->update(['status' => LeaveRequestStatus::Rejected->value, 'rejected_at' => now()]);
            } else {
                $locked->update(['status' => LeaveRequestStatus::RevisionRequested->value]);
            }

            $requester = $locked->requester;
            if ($requester instanceof User) {
                $requester->notify(new LeaveRequestReviewed($locked));
            }
            activity('leave')->causedBy($actor)->performedOn($locked)->event('leave_request_'.$locked->requestStatus()->value)
                ->withProperties(['leave_request_public_id' => $locked->public_id, 'approval_instance_public_id' => $updatedInstance->public_id])
                ->log('Leave approval action recorded.');

            return $locked->refresh()->load(['type', 'approvalInstance.steps', 'ledgerEntries']);
        });
    }

    public function cancel(User $actor, LeaveRequest $request): LeaveRequest
    {
        return DB::transaction(function () use ($actor, $request): LeaveRequest {
            $locked = LeaveRequest::query()->with('approvalInstance')->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            abort_unless((int) $locked->requested_by === (int) $actor->getKey(), 403);
            if (! $locked->requestStatus()->isPending()) {
                throw ValidationException::withMessages(['request' => __('leave.validation.already_reviewed')]);
            }
            $instance = $locked->approvalInstance;
            abort_unless($instance instanceof ApprovalInstance, 409);
            $this->approvals->cancel($actor, $instance);
            $locked->update(['status' => LeaveRequestStatus::Cancelled->value, 'cancelled_at' => now()]);

            activity('leave')->causedBy($actor)->performedOn($locked)->event('leave_request_cancelled')
                ->withProperties(['leave_request_public_id' => $locked->public_id])->log('Pending leave request cancelled.');

            return $locked->refresh();
        });
    }

    public function expireDue(CarbonInterface|string $asOf): int
    {
        $date = $asOf instanceof CarbonInterface ? CarbonImmutable::instance($asOf) : CarbonImmutable::parse($asOf);
        $expired = 0;
        LeaveEntitlement::query()->where('status', 'active')->whereNotNull('valid_to')
            ->whereDate('valid_to', '<', $date->toDateString())->orderBy('id')->chunkById(100, function ($entitlements) use (&$expired): void {
                foreach ($entitlements as $entitlement) {
                    DB::transaction(function () use ($entitlement, &$expired): void {
                        $locked = LeaveEntitlement::query()->with(['employee.user'])->whereKey($entitlement->getKey())->lockForUpdate()->firstOrFail();
                        $balance = $this->toHundredths((string) LeaveLedgerEntry::query()
                            ->where('leave_entitlement_id', $locked->getKey())->sum('quantity'));
                        if ($balance > 0) {
                            LeaveLedgerEntry::query()->firstOrCreate([
                                'leave_entitlement_id' => $locked->getKey(),
                                'reference_key' => 'EXPIRY:'.$locked->public_id,
                            ], [
                                'legal_entity_id' => $locked->legal_entity_id,
                                'employee_id' => $locked->employee_id,
                                'entry_type' => LeaveLedgerEntryType::Expiry->value,
                                'quantity' => $this->fromHundredths(-$balance),
                                'effective_date' => $locked->valid_to,
                                'reason' => 'Effective-dated leave entitlement expired.',
                                'created_by' => $locked->created_by,
                            ]);
                        }
                        $locked->update(['status' => 'expired']);
                        $expired++;
                    });
                }
            });

        return $expired;
    }

    public function notifyExpiring(int $daysAhead = 30): int
    {
        $target = now()->addDays($daysAhead)->toDateString();
        $count = 0;
        LeaveEntitlement::query()->with('employee.user')->where('status', 'active')->whereDate('valid_to', $target)
            ->chunkById(100, function ($entitlements) use (&$count): void {
                foreach ($entitlements as $entitlement) {
                    $user = $entitlement->employee?->user;
                    if ($user instanceof User) {
                        $user->notify(new LeaveBalanceExpiring($entitlement));
                        $count++;
                    }
                }
            });

        return $count;
    }

    private function assertEligible(Employee $employee, LeavePolicy $policy, CarbonImmutable $start): void
    {
        $employment = EmploymentHistory::query()->where('employee_id', $employee->getKey())
            ->effectiveOn($start->toDateString())->first();
        if (! $employment || ! is_string($employment->getRawOriginal('join_date'))) {
            throw ValidationException::withMessages(['start_date' => __('leave.validation.missing_employment')]);
        }
        $joinDate = CarbonImmutable::parse((string) $employment->getRawOriginal('join_date'));
        if ($start->lessThan($joinDate->addMonths((int) $policy->eligibility_months))) {
            throw ValidationException::withMessages(['start_date' => __('leave.validation.not_eligible')]);
        }
    }

    private function workingDays(Employee $employee, CarbonImmutable $start, CarbonImmutable $end): int
    {
        $days = 0;
        for ($date = $start; $date->lessThanOrEqualTo($end); $date = $date->addDay()) {
            $history = EmploymentHistory::query()->where('employee_id', $employee->getKey())
                ->effectiveOn($date->toDateString())->first();
            if (! $history) {
                throw ValidationException::withMessages(['start_date' => __('leave.validation.missing_employment')]);
            }
            $schedule = $this->scheduleFor($employee, $history, $date);
            $scheduleDay = $schedule->days()->where('day_of_week', $date->dayOfWeekIso)->first();
            if (! $scheduleDay?->is_working_day) {
                continue;
            }
            $holiday = Holiday::query()->where('legal_entity_id', $employee->legal_entity_id)
                ->whereDate('holiday_date', $date->toDateString())->where('status', 'active')
                ->where(fn (Builder $query) => $query->whereNull('branch_id')->orWhere('branch_id', $history->branch_id))->exists();
            if (! $holiday) {
                $days++;
            }
        }

        return $days;
    }

    private function scheduleFor(Employee $employee, EmploymentHistory $history, CarbonImmutable $date): WorkSchedule
    {
        $assignment = EmployeeScheduleAssignment::query()->where('employee_id', $employee->getKey())
            ->whereDate('effective_from', '<=', $date->toDateString())
            ->where(fn (Builder $period) => $period->whereNull('effective_to')->orWhereDate('effective_to', '>', $date->toDateString()))
            ->latest('effective_from')->first();
        if ($assignment?->schedule instanceof WorkSchedule) {
            return $assignment->schedule;
        }

        $base = WorkSchedule::query()->where('legal_entity_id', $employee->legal_entity_id)
            ->where('status', 'active')->whereDate('effective_from', '<=', $date->toDateString())
            ->where(fn (Builder $period) => $period->whereNull('effective_to')->orWhereDate('effective_to', '>', $date->toDateString()));
        $schedule = (clone $base)->where('department_id', $history->department_id)->latest('effective_from')->first()
            ?? (clone $base)->whereNull('department_id')->where('branch_id', $history->branch_id)->latest('effective_from')->first()
            ?? (clone $base)->whereNull('department_id')->whereNull('branch_id')->latest('effective_from')->first();
        if (! $schedule) {
            throw ValidationException::withMessages(['start_date' => __('leave.validation.missing_schedule')]);
        }

        return $schedule;
    }

    private function assertAvailableBalance(Employee $employee, LeaveType $type, CarbonImmutable $end, int $days): void
    {
        $available = LeaveEntitlement::query()->where('employee_id', $employee->getKey())
            ->where('leave_type_id', $type->getKey())->usableOn($end->toDateString())
            ->get()->sum(fn (LeaveEntitlement $entitlement): int => max(0, $this->toHundredths((string) LeaveLedgerEntry::query()
            ->where('leave_entitlement_id', $entitlement->getKey())->sum('quantity'))));
        if ($available < $days * 100) {
            throw ValidationException::withMessages(['leave_type_public_id' => __('leave.validation.insufficient_balance')]);
        }
    }

    private function postUsage(User $actor, LeaveRequest $request): void
    {
        $remaining = $this->toHundredths((string) $request->total_days);
        $requestEndDate = CarbonImmutable::parse((string) $request->getRawOriginal('end_date'))->toDateString();
        $entitlements = LeaveEntitlement::query()->where('employee_id', $request->employee_id)
            ->where('leave_type_id', $request->leave_type_id)->usableOn($requestEndDate)
            ->orderByRaw('valid_to IS NULL, valid_to')->orderBy('valid_from')->lockForUpdate()->get();
        foreach ($entitlements as $entitlement) {
            if ($remaining <= 0) {
                break;
            }
            $balance = $this->toHundredths((string) LeaveLedgerEntry::query()
                ->where('leave_entitlement_id', $entitlement->getKey())->sum('quantity'));
            $used = min(max(0, $balance), $remaining);
            if ($used === 0) {
                continue;
            }
            LeaveLedgerEntry::query()->create([
                'legal_entity_id' => $request->legal_entity_id,
                'leave_entitlement_id' => $entitlement->getKey(),
                'employee_id' => $request->employee_id,
                'leave_request_id' => $request->getKey(),
                'entry_type' => LeaveLedgerEntryType::Usage->value,
                'quantity' => $this->fromHundredths(-$used),
                'effective_date' => $request->start_date,
                'reference_key' => 'USAGE:'.$request->public_id.':'.$entitlement->public_id,
                'reason' => 'Approved leave usage.',
                'created_by' => $actor->getKey(),
            ]);
            $remaining -= $used;
        }
        if ($remaining > 0) {
            throw ValidationException::withMessages(['decision' => __('leave.validation.balance_changed')]);
        }
    }

    private function decimal(string $value): string
    {
        return $this->fromHundredths($this->toHundredths($value));
    }

    private function nullableDecimal(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? $this->decimal((string) $value) : null;
    }

    private function toHundredths(string $value): int
    {
        $normalized = trim($value);
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '+-');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $hundredths = ((int) ($whole === '' ? '0' : $whole) * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');

        return $negative ? -$hundredths : $hundredths;
    }

    private function fromHundredths(int $value): string
    {
        $negative = $value < 0 ? '-' : '';
        $absolute = abs($value);

        return $negative.intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    private function nullableInteger(mixed $value): ?int
    {
        return is_numeric($value) && trim((string) $value) !== '' ? (int) $value : null;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
