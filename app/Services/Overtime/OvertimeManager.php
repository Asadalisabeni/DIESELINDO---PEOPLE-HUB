<?php

namespace App\Services\Overtime;

use App\Enums\ApprovalInstanceStatus;
use App\Enums\EmployeeStatus;
use App\Enums\OvertimeDayType;
use App\Enums\OvertimeRequestStatus;
use App\Models\ApprovalDefinition;
use App\Models\ApprovalInstance;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeScheduleAssignment;
use App\Models\EmploymentHistory;
use App\Models\Holiday;
use App\Models\LegalEntity;
use App\Models\OvertimeCalculation;
use App\Models\OvertimeRequest;
use App\Models\OvertimeRule;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Notifications\OvertimeApprovalPending;
use App\Notifications\OvertimeRequestReviewed;
use App\Services\Approval\ApprovalEngine;
use App\Services\Organization\LegalEntityScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OvertimeManager
{
    public function __construct(
        private readonly LegalEntityScope $scope,
        private readonly ApprovalEngine $approvals,
    ) {}

    /** @param array<string, mixed> $data */
    public function createRule(User $actor, LegalEntity $entity, array $data): OvertimeRule
    {
        abort_unless($actor->can('overtime.manage') && $this->scope->manages($actor, $entity->getKey()), 403);
        $segments = $this->validatedSegments($data['segment_rules'] ?? null);

        return DB::transaction(function () use ($actor, $entity, $data, $segments): OvertimeRule {
            $overlap = OvertimeRule::query()->where('legal_entity_id', $entity->getKey())
                ->where('day_type', $data['day_type'])->where('status', 'active')
                ->whereDate('effective_from', '<=', $data['effective_to'] ?? '9999-12-31')
                ->where(fn (Builder $period) => $period->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $data['effective_from']))
                ->lockForUpdate()->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['effective_from' => __('overtime.validation.rule_overlap')]);
            }

            $rule = OvertimeRule::query()->create([
                'legal_entity_id' => $entity->getKey(), 'code' => strtoupper(trim((string) $data['code'])),
                'name' => trim((string) $data['name']), 'day_type' => $data['day_type'],
                'calculation_method' => $data['calculation_method'],
                'minimum_minutes' => (int) $data['minimum_minutes'],
                'rounding_increment_minutes' => (int) $data['rounding_increment_minutes'],
                'rounding_mode' => $data['rounding_mode'], 'maximum_minutes' => (int) $data['maximum_minutes'],
                'segment_rules' => $segments, 'meal_threshold_minutes' => $data['meal_threshold_minutes'] ?? null,
                'meal_allowance_idr' => (int) ($data['meal_allowance_idr'] ?? 0),
                'transport_threshold_minutes' => $data['transport_threshold_minutes'] ?? null,
                'transport_allowance_idr' => (int) ($data['transport_allowance_idr'] ?? 0),
                'eligibility' => $data['eligibility'], 'effective_from' => $data['effective_from'],
                'effective_to' => $data['effective_to'] ?? null, 'status' => 'active',
                'approved_by' => $actor->getKey(), 'approved_at' => now(), 'created_by' => $actor->getKey(),
            ]);
            $this->ensureDefinition($actor, $entity, (string) $data['effective_from']);
            activity('overtime')->causedBy($actor)->performedOn($rule)->event('overtime_rule_created')
                ->withProperties([
                    'legal_entity_public_id' => $entity->public_id, 'rule_public_id' => $rule->public_id,
                    'day_type' => $rule->dayType()->value, 'effective_from' => $data['effective_from'],
                    'effective_to' => $data['effective_to'] ?? null,
                ])->log('Effective-dated overtime rule created.');

            return $rule;
        });
    }

    /** @param array<string, mixed> $data */
    public function submit(User $actor, Employee $employee, array $data): OvertimeRequest
    {
        abort_unless($actor->can('overtime.request'), 403);
        $timezone = (string) $employee->legalEntity()->value('timezone');
        $timezone = $timezone !== '' ? $timezone : 'Asia/Jakarta';
        $start = CarbonImmutable::parse((string) $data['planned_start'], $timezone);
        $end = CarbonImmutable::parse((string) $data['planned_end'], $timezone);
        if (! $start->isSameDay($end) || $end->lessThanOrEqualTo($start) || $start->lessThanOrEqualTo(now($timezone))) {
            throw ValidationException::withMessages(['planned_start' => __('overtime.validation.must_be_planned')]);
        }
        $this->assertCanRequestFor($actor, $employee, $start->toDateString());
        $dayType = $this->dayTypeFor($employee, $start);
        $rule = OvertimeRule::query()->where('legal_entity_id', $employee->legal_entity_id)
            ->where('day_type', $dayType->value)->where('status', 'active')
            ->effectiveOn($start->toDateString())->latest('effective_from')->first();
        if (! $rule) {
            throw ValidationException::withMessages(['planned_start' => __('overtime.validation.missing_rule')]);
        }
        $this->assertEligibility($employee, $rule, $start->toDateString());
        $plannedMinutes = $start->diffInMinutes($end);
        if ($plannedMinutes > (int) $rule->maximum_minutes) {
            throw ValidationException::withMessages(['planned_end' => __('overtime.validation.exceeds_maximum')]);
        }
        $fingerprint = hash('sha256', implode('|', [
            $employee->getKey(), $start->utc()->toIso8601String(), $end->utc()->toIso8601String(), $data['request_type'],
        ]));

        return DB::transaction(function () use ($actor, $employee, $data, $start, $end, $dayType, $rule, $plannedMinutes, $fingerprint): OvertimeRequest {
            $overlap = OvertimeRequest::query()->where('employee_id', $employee->getKey())
                ->whereNotIn('status', [OvertimeRequestStatus::Rejected->value, OvertimeRequestStatus::Cancelled->value])
                ->where('planned_start_at', '<', $end->utc())->where('planned_end_at', '>', $start->utc())
                ->lockForUpdate()->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['planned_start' => __('overtime.validation.overlap')]);
            }
            $request = OvertimeRequest::query()->create([
                'legal_entity_id' => $employee->legal_entity_id, 'employee_id' => $employee->getKey(),
                'overtime_rule_id' => $rule->getKey(), 'requested_by' => $actor->getKey(),
                'request_type' => $data['request_type'], 'day_type_snapshot' => $dayType->value,
                'work_date' => $start->toDateString(), 'planned_start_at' => $start->utc(),
                'planned_end_at' => $end->utc(), 'planned_minutes' => $plannedMinutes,
                'reason' => trim((string) $data['reason']), 'work_description' => trim((string) $data['work_description']),
                'status' => OvertimeRequestStatus::PendingManager->value, 'request_fingerprint' => $fingerprint,
                'submitted_at' => now(),
            ]);
            $definition = $this->ensureDefinition($actor, $employee->legalEntity()->firstOrFail(), $start->toDateString());
            $instance = $this->approvals->start($actor, $employee, 'overtime_request', $request->public_id, [
                'employee_public_id' => $employee->public_id, 'work_date' => $start->toDateString(),
                'day_type' => $dayType->value, 'planned_minutes' => $plannedMinutes,
            ], $definition);
            $request->update(['approval_instance_id' => $instance->getKey()]);
            Notification::send($this->approvals->recipientsForCurrentStep($instance), new OvertimeApprovalPending($request));
            activity('overtime')->causedBy($actor)->performedOn($request)->event('overtime_request_submitted')
                ->withProperties([
                    'legal_entity_public_id' => $employee->legalEntity()->value('public_id'),
                    'employee_public_id' => $employee->public_id, 'request_public_id' => $request->public_id,
                    'work_date' => $start->toDateString(), 'day_type' => $dayType->value,
                    'planned_minutes' => $plannedMinutes,
                ])->log('Overtime request submitted before work started.');

            return $request->refresh()->load(['rule', 'approvalInstance.steps']);
        });
    }

    /** @param array<string, mixed> $data */
    public function review(User $actor, OvertimeRequest $request, array $data): OvertimeRequest
    {
        return DB::transaction(function () use ($actor, $request, $data): OvertimeRequest {
            $locked = OvertimeRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            $instance = ApprovalInstance::query()->whereKey($locked->approval_instance_id)->lockForUpdate()->firstOrFail();
            $stepOrder = (int) $instance->current_step_order;
            $decision = (string) $data['decision'];

            if ($stepOrder === 1 && $decision === 'approve') {
                if (now()->greaterThanOrEqualTo($locked->plannedStartAt())) {
                    throw ValidationException::withMessages(['decision' => __('overtime.validation.late_approval')]);
                }
                $approved = (int) ($data['approved_minutes'] ?? $locked->planned_minutes);
                if ($approved < 1 || $approved > (int) $locked->planned_minutes || $approved > (int) $locked->rule()->value('maximum_minutes')) {
                    throw ValidationException::withMessages(['approved_minutes' => __('overtime.validation.invalid_approved')]);
                }
                $locked->update(['approved_minutes' => $approved]);
            }
            if ($stepOrder === 2 && $decision === 'approve') {
                $this->calculateActual($actor, $locked, trim((string) $data['review_notes']));
            }
            if ($stepOrder === 3 && $decision === 'approve') {
                $period = trim((string) ($data['payroll_period_key'] ?? ''));
                if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
                    throw ValidationException::withMessages(['payroll_period_key' => __('overtime.validation.invalid_payroll_period')]);
                }
                if (! $locked->calculation()->where('payroll_eligible', true)->exists()) {
                    throw ValidationException::withMessages(['decision' => __('overtime.validation.not_payroll_eligible')]);
                }
            }

            $updatedInstance = $this->approvals->act($actor, $instance, [
                'decision' => $decision, 'review_notes' => (string) $data['review_notes'],
                'idempotency_key' => (string) ($data['idempotency_key'] ?? Str::ulid()),
            ]);
            $instanceStatus = $updatedInstance->instanceStatus();
            if ($decision === 'approve' && $instanceStatus === ApprovalInstanceStatus::Pending) {
                if ($stepOrder === 1) {
                    $locked->update(['status' => OvertimeRequestStatus::ApprovedWaitingActual->value, 'approved_at' => now()]);
                } elseif ($stepOrder === 2) {
                    $locked->update(['status' => OvertimeRequestStatus::PendingPayroll->value, 'validated_at' => now()]);
                }
                Notification::send($this->approvals->recipientsForCurrentStep($updatedInstance), new OvertimeApprovalPending($locked));
            } elseif ($decision === 'approve' && $instanceStatus === ApprovalInstanceStatus::Approved) {
                $locked->update([
                    'status' => OvertimeRequestStatus::PayrollEligible->value,
                    'payroll_period_key' => trim((string) $data['payroll_period_key']), 'payroll_eligible_at' => now(),
                ]);
            } elseif ($instanceStatus === ApprovalInstanceStatus::Rejected) {
                $locked->update(['status' => OvertimeRequestStatus::Rejected->value, 'rejected_at' => now()]);
            } else {
                $locked->update(['status' => OvertimeRequestStatus::RevisionRequested->value]);
            }

            $requester = $locked->requester;
            if ($requester instanceof User) {
                $requester->notify(new OvertimeRequestReviewed($locked));
            }
            activity('overtime')->causedBy($actor)->performedOn($locked)->event('overtime_request_'.$locked->requestStatus()->value)
                ->withProperties([
                    'request_public_id' => $locked->public_id,
                    'approval_instance_public_id' => $updatedInstance->public_id,
                    'step_order' => $stepOrder,
                ])->log('Overtime workflow action recorded.');

            return $locked->refresh()->load(['rule', 'attendanceRecord', 'calculation', 'approvalInstance.steps']);
        });
    }

    public function cancel(User $actor, OvertimeRequest $request): OvertimeRequest
    {
        return DB::transaction(function () use ($actor, $request): OvertimeRequest {
            $locked = OvertimeRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            abort_unless((int) $locked->requested_by === (int) $actor->getKey(), 403);
            if (now()->greaterThanOrEqualTo($locked->plannedStartAt())) {
                throw ValidationException::withMessages(['request' => __('overtime.validation.cannot_cancel_started')]);
            }
            $instance = ApprovalInstance::query()->findOrFail($locked->approval_instance_id);
            $this->approvals->cancel($actor, $instance);
            $locked->update(['status' => OvertimeRequestStatus::Cancelled->value, 'cancelled_at' => now()]);

            return $locked->refresh();
        });
    }

    private function ensureDefinition(User $actor, LegalEntity $entity, string $effectiveFrom): ApprovalDefinition
    {
        return $this->approvals->createDefinition(
            $actor, $entity->getKey(), 'overtime.standard', 'overtime_request', $effectiveFrom,
            false, 24, 72, [
                ['step_order' => 1, 'name' => 'Direct manager', 'resolver_type' => 'direct_manager', 'required_permission' => 'overtime.approve-manager'],
                ['step_order' => 2, 'name' => 'HR actual validation', 'resolver_type' => 'scoped_permission', 'required_permission' => 'overtime.validate'],
                ['step_order' => 3, 'name' => 'Payroll inclusion', 'resolver_type' => 'scoped_permission', 'required_permission' => 'overtime.include-payroll'],
            ],
        );
    }

    private function calculateActual(User $actor, OvertimeRequest $request, string $note): OvertimeCalculation
    {
        if (now()->lessThan($request->plannedEndAt())) {
            throw ValidationException::withMessages(['decision' => __('overtime.validation.actual_not_ready')]);
        }
        $record = AttendanceRecord::query()->where('legal_entity_id', $request->legal_entity_id)
            ->where('employee_id', $request->employee_id)->whereDate('work_date', $request->work_date)
            ->where('is_current', true)->lockForUpdate()->first();
        if (! $record || in_array($record->status, ['incomplete', 'anomalous'], true)
            || ! $record->checkInAt() || ! $record->checkOutAt()) {
            throw ValidationException::withMessages(['decision' => __('overtime.validation.actual_not_ready')]);
        }
        $rule = $request->rule()->firstOrFail();
        if ($request->dayType() === OvertimeDayType::WorkingDay) {
            $actualStart = $record->scheduledEndAt();
            if (! $actualStart || $record->checkInAt()->greaterThan($actualStart)) {
                $actualStart = $record->checkInAt();
            }
        } else {
            $actualStart = $record->checkInAt();
        }
        $actualEnd = $record->checkOutAt();
        $actualMinutes = $actualEnd->greaterThan($actualStart) ? $this->minutesBetween($actualStart, $actualEnd) : 0;
        $candidate = min($actualMinutes, (int) $request->approved_minutes, (int) $rule->maximum_minutes);
        $payable = $candidate < (int) $rule->minimum_minutes ? 0 : $this->roundMinutes(
            $candidate, (int) $rule->rounding_increment_minutes, (string) $rule->rounding_mode,
        );
        $payable = min($payable, (int) $request->approved_minutes, (int) $rule->maximum_minutes);
        [$weighted, $segmentTrace] = $this->weightedMinutes($payable, $this->validatedSegments($rule->segmentRules()));
        $mealEligible = $rule->meal_threshold_minutes !== null && $payable >= (int) $rule->meal_threshold_minutes;
        $transportEligible = $rule->transport_threshold_minutes !== null && $payable >= (int) $rule->transport_threshold_minutes;
        $snapshot = [
            'rule_public_id' => $rule->public_id, 'method' => $rule->calculation_method,
            'day_type' => $rule->dayType()->value, 'minimum_minutes' => (int) $rule->minimum_minutes,
            'rounding_increment_minutes' => (int) $rule->rounding_increment_minutes,
            'rounding_mode' => $rule->rounding_mode, 'maximum_minutes' => (int) $rule->maximum_minutes,
            'segment_rules' => $rule->segmentRules(), 'meal_threshold_minutes' => $rule->meal_threshold_minutes,
            'meal_allowance_idr' => (int) $rule->meal_allowance_idr,
            'transport_threshold_minutes' => $rule->transport_threshold_minutes,
            'transport_allowance_idr' => (int) $rule->transport_allowance_idr,
            'effective_from' => $rule->effectiveFrom()->toDateString(),
        ];
        $encoded = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $calculation = OvertimeCalculation::query()->create([
            'legal_entity_id' => $request->legal_entity_id, 'overtime_request_id' => $request->getKey(),
            'overtime_rule_id' => $rule->getKey(), 'attendance_record_id' => $record->getKey(),
            'planned_minutes' => $request->planned_minutes, 'approved_minutes' => $request->approved_minutes,
            'actual_minutes' => $actualMinutes, 'payable_minutes' => $payable,
            'weighted_minutes_hundredths' => $weighted, 'meal_eligible' => $mealEligible,
            'meal_allowance_idr' => $mealEligible ? (int) $rule->meal_allowance_idr : 0,
            'transport_eligible' => $transportEligible,
            'transport_allowance_idr' => $transportEligible ? (int) $rule->transport_allowance_idr : 0,
            'payroll_eligible' => $payable > 0, 'rule_snapshot' => $snapshot,
            'calculation_trace' => [
                'candidate_minutes' => $candidate, 'payable_minutes' => $payable,
                'rounding_mode' => $rule->rounding_mode, 'segments' => $segmentTrace,
            ],
            'rule_checksum' => hash('sha256', $encoded), 'calculated_by' => $actor->getKey(), 'calculated_at' => now(),
        ]);
        $request->update([
            'attendance_record_id' => $record->getKey(), 'actual_start_at' => $actualStart,
            'actual_end_at' => $actualEnd, 'actual_minutes' => $actualMinutes, 'payable_minutes' => $payable,
            'weighted_minutes_hundredths' => $weighted, 'meal_eligible' => $mealEligible,
            'meal_allowance_idr' => $mealEligible ? (int) $rule->meal_allowance_idr : 0,
            'transport_eligible' => $transportEligible,
            'transport_allowance_idr' => $transportEligible ? (int) $rule->transport_allowance_idr : 0,
            'validation_note' => $note,
        ]);

        return $calculation;
    }

    private function dayTypeFor(Employee $employee, CarbonImmutable $date): OvertimeDayType
    {
        $history = EmploymentHistory::query()->where('employee_id', $employee->getKey())
            ->effectiveOn($date->toDateString())->first();
        if (! $history) {
            throw ValidationException::withMessages(['planned_start' => __('overtime.validation.missing_employment')]);
        }
        $holiday = Holiday::query()->where('legal_entity_id', $employee->legal_entity_id)
            ->whereDate('holiday_date', $date->toDateString())->where('status', 'active')
            ->where(fn (Builder $query) => $query->whereNull('branch_id')->orWhere('branch_id', $history->branch_id))->exists();
        if ($holiday) {
            return OvertimeDayType::NationalHoliday;
        }
        $schedule = $this->scheduleFor($employee, $history, $date);
        $working = (bool) $schedule->days()->where('day_of_week', $date->dayOfWeekIso)->value('is_working_day');

        return $working ? OvertimeDayType::WorkingDay : OvertimeDayType::RestDay;
    }

    private function scheduleFor(Employee $employee, EmploymentHistory $history, CarbonImmutable $date): WorkSchedule
    {
        $assignment = EmployeeScheduleAssignment::query()->where('employee_id', $employee->getKey())
            ->effectiveOn($date->toDateString())->with('schedule')->latest('effective_from')->first();
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
            throw ValidationException::withMessages(['planned_start' => __('overtime.validation.missing_schedule')]);
        }

        return $schedule;
    }

    private function assertCanRequestFor(User $actor, Employee $employee, string $date): void
    {
        if ((int) $actor->employee_id === (int) $employee->getKey()) {
            return;
        }
        abort_unless($actor->can('overtime.team.request') && $actor->employee_id !== null, 403);
        $directReport = EmploymentHistory::query()->where('employee_id', $employee->getKey())
            ->where('manager_employee_id', $actor->employee_id)->effectiveOn($date)->exists();
        abort_unless($directReport, 404);
    }

    private function assertEligibility(Employee $employee, OvertimeRule $rule, string $date): void
    {
        if ($employee->employeeStatus() !== EmployeeStatus::Active) {
            throw ValidationException::withMessages(['planned_start' => __('overtime.validation.not_eligible')]);
        }
        if ($rule->eligibility === 'all_active') {
            return;
        }
        $employmentStatus = EmploymentHistory::query()->where('employee_id', $employee->getKey())
            ->effectiveOn($date)->value('employment_status');
        if ($employmentStatus !== $rule->eligibility) {
            throw ValidationException::withMessages(['planned_start' => __('overtime.validation.not_eligible')]);
        }
    }

    /** @return list<array{up_to_minutes: ?int, multiplier_hundredths: int}> */
    private function validatedSegments(mixed $value): array
    {
        if (! is_array($value) || $value === []) {
            throw ValidationException::withMessages(['segment_rules' => __('overtime.validation.invalid_segments')]);
        }
        $segments = [];
        $lastCap = 0;
        foreach (array_values($value) as $index => $segment) {
            if (! is_array($segment) || ! array_key_exists('up_to_minutes', $segment)
                || ! isset($segment['multiplier_hundredths']) || ! is_numeric($segment['multiplier_hundredths'])) {
                throw ValidationException::withMessages(['segment_rules' => __('overtime.validation.invalid_segments')]);
            }
            $cap = $segment['up_to_minutes'] === null ? null : (int) $segment['up_to_minutes'];
            $multiplier = (int) $segment['multiplier_hundredths'];
            if ($multiplier < 1 || ($cap !== null && $cap <= $lastCap)
                || ($cap === null && $index !== array_key_last($value))) {
                throw ValidationException::withMessages(['segment_rules' => __('overtime.validation.invalid_segments')]);
            }
            $segments[] = ['up_to_minutes' => $cap, 'multiplier_hundredths' => $multiplier];
            if ($cap !== null) {
                $lastCap = $cap;
            }
        }
        if ($segments[array_key_last($segments)]['up_to_minutes'] !== null) {
            throw ValidationException::withMessages(['segment_rules' => __('overtime.validation.invalid_segments')]);
        }

        return $segments;
    }

    /** @param list<array{up_to_minutes: ?int, multiplier_hundredths: int}> $segments
     * @return array{int, list<array{minutes: int, multiplier_hundredths: int, weighted_minutes_hundredths: int}>}
     */
    private function weightedMinutes(int $payable, array $segments): array
    {
        $remaining = $payable;
        $previousCap = 0;
        $weighted = 0;
        $trace = [];
        foreach ($segments as $segment) {
            if ($remaining === 0) {
                break;
            }
            $capacity = $segment['up_to_minutes'] === null ? $remaining : $segment['up_to_minutes'] - $previousCap;
            $minutes = min($remaining, $capacity);
            $result = $minutes * $segment['multiplier_hundredths'];
            $weighted += $result;
            $trace[] = ['minutes' => $minutes, 'multiplier_hundredths' => $segment['multiplier_hundredths'], 'weighted_minutes_hundredths' => $result];
            $remaining -= $minutes;
            if ($segment['up_to_minutes'] !== null) {
                $previousCap = $segment['up_to_minutes'];
            }
        }

        return [$weighted, $trace];
    }

    private function roundMinutes(int $minutes, int $increment, string $mode): int
    {
        return match ($mode) {
            'ceil' => intdiv($minutes + $increment - 1, $increment) * $increment,
            'nearest' => intdiv($minutes + intdiv($increment, 2), $increment) * $increment,
            default => intdiv($minutes, $increment) * $increment,
        };
    }

    private function minutesBetween(CarbonImmutable $start, CarbonImmutable $end): int
    {
        return intdiv($end->getTimestamp() - $start->getTimestamp(), 60);
    }
}
