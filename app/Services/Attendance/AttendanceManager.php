<?php

namespace App\Services\Attendance;

use App\Domain\Attendance\AttendanceSourceAdapter;
use App\Domain\Attendance\WebGpsSourceAdapter;
use App\Enums\AttendanceCorrectionStatus;
use App\Enums\AttendanceEventStatus;
use App\Enums\AttendanceEventType;
use App\Enums\AttendanceSourceType;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSource;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeScheduleAssignment;
use App\Models\Holiday;
use App\Models\LegalEntity;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\Organization\LegalEntityScope;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceManager
{
    public function __construct(private readonly LegalEntityScope $scope) {}

    /** @param array<string, mixed> $data */
    public function createSchedule(User $actor, LegalEntity $entity, array $data): WorkSchedule
    {
        $this->assertManaged($actor, $entity);

        return DB::transaction(function () use ($actor, $entity, $data): WorkSchedule {
            if (WorkSchedule::query()->where('legal_entity_id', $entity->getKey())
                ->where('code', strtoupper((string) $data['code']))->exists()) {
                throw ValidationException::withMessages(['code' => __('attendance.validation.schedule_code_exists')]);
            }
            $branch = empty($data['branch_public_id']) ? null : Branch::query()
                ->where('legal_entity_id', $entity->getKey())->where('public_id', $data['branch_public_id'])->firstOrFail();
            $department = empty($data['department_public_id']) ? null : Department::query()
                ->where('legal_entity_id', $entity->getKey())->where('public_id', $data['department_public_id'])->firstOrFail();
            if ($department && $branch && (int) $department->branch_id !== (int) $branch->getKey()) {
                throw ValidationException::withMessages(['department_public_id' => __('attendance.validation.invalid_schedule_scope')]);
            }
            $schedule = WorkSchedule::query()->create([
                'legal_entity_id' => $entity->getKey(),
                'branch_id' => $branch?->getKey(),
                'department_id' => $department?->getKey(),
                'code' => strtoupper((string) $data['code']),
                'name' => $data['name'],
                'timezone' => $data['timezone'],
                'late_grace_minutes' => $data['late_grace_minutes'],
                'early_leave_grace_minutes' => $data['early_leave_grace_minutes'],
                'effective_from' => $data['effective_from'],
                'effective_to' => $data['effective_to'] ?? null,
                'status' => 'active',
                'created_by' => $actor->getKey(),
            ]);

            foreach ($data['days'] as $day => $dayData) {
                $working = (bool) ($dayData['is_working_day'] ?? false);
                $schedule->days()->create([
                    'day_of_week' => (int) $day,
                    'is_working_day' => $working,
                    'start_time' => $working ? $dayData['start_time'] : null,
                    'end_time' => $working ? $dayData['end_time'] : null,
                    'break_minutes' => $working ? (int) ($dayData['break_minutes'] ?? 0) : 0,
                    'crosses_midnight' => false,
                ]);
            }

            activity('attendance')->causedBy($actor)->performedOn($schedule)
                ->event('work_schedule_created')
                ->withProperties(['entity_public_id' => $entity->public_id, 'schedule_public_id' => $schedule->public_id])
                ->log('Configurable work schedule created.');

            return $schedule->load('days');
        });
    }

    /** @param array<string, mixed> $data */
    public function createSource(User $actor, LegalEntity $entity, array $data): AttendanceSource
    {
        $this->assertManaged($actor, $entity);

        if (AttendanceSource::query()->where('legal_entity_id', $entity->getKey())
            ->where('code', strtoupper((string) $data['code']))->exists()) {
            throw ValidationException::withMessages(['code' => __('attendance.validation.source_code_exists')]);
        }

        $source = AttendanceSource::query()->create([
            'legal_entity_id' => $entity->getKey(),
            'code' => strtoupper((string) $data['code']),
            'name' => $data['name'],
            'type' => $data['type'],
            'adapter' => $data['adapter'],
            'validation_rules' => [
                'requires_gps' => (bool) ($data['requires_gps'] ?? false),
                'max_gps_accuracy_meters' => (int) ($data['max_gps_accuracy_meters'] ?? 150),
                'max_offline_delay_minutes' => (int) ($data['max_offline_delay_minutes'] ?? 720),
                'requires_selfie' => (bool) ($data['requires_selfie'] ?? false),
            ],
            'status' => 'active',
            'created_by' => $actor->getKey(),
        ]);

        activity('attendance')->causedBy($actor)->performedOn($source)
            ->event('attendance_source_created')
            ->withProperties(['entity_public_id' => $entity->public_id, 'source_public_id' => $source->public_id, 'type' => $source->sourceType()->value])
            ->log('Attendance source configured without connection secrets.');

        return $source;
    }

    /** @param array<string, mixed> $data */
    public function createHoliday(User $actor, LegalEntity $entity, array $data): Holiday
    {
        $this->assertManaged($actor, $entity);
        $branch = empty($data['branch_public_id']) ? null : Branch::query()
            ->where('legal_entity_id', $entity->getKey())->where('public_id', $data['branch_public_id'])->firstOrFail();

        $duplicate = Holiday::query()->where('legal_entity_id', $entity->getKey())
            ->where('holiday_date', $data['holiday_date'])
            ->where('name', $data['name'])
            ->where(function (Builder $query) use ($branch): void {
                $branch instanceof Branch
                    ? $query->where('branch_id', $branch->getKey())
                    : $query->whereNull('branch_id');
            })->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['holiday_date' => __('attendance.validation.holiday_exists')]);
        }

        return Holiday::query()->create([
            'legal_entity_id' => $entity->getKey(),
            'branch_id' => $branch?->getKey(),
            'holiday_date' => $data['holiday_date'],
            'name' => $data['name'],
            'type' => $data['type'],
            'source' => $data['source'] ?? null,
            'status' => 'active',
            'created_by' => $actor->getKey(),
        ]);
    }

    public function assignSchedule(
        User $actor,
        Employee $employee,
        WorkSchedule $schedule,
        string $effectiveFrom,
        ?string $effectiveTo,
        string $reason,
    ): EmployeeScheduleAssignment {
        $entity = $employee->legalEntity;
        abort_unless($entity instanceof LegalEntity, 422);
        $this->assertManaged($actor, $entity);
        if ((int) $schedule->legal_entity_id !== (int) $employee->legal_entity_id) {
            throw ValidationException::withMessages(['work_schedule_public_id' => __('attendance.validation.cross_entity_schedule')]);
        }

        $overlap = EmployeeScheduleAssignment::query()
            ->where('employee_id', $employee->getKey())
            ->whereDate('effective_from', '<=', $effectiveTo ?? '9999-12-31')
            ->where(fn (Builder $query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $effectiveFrom))
            ->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['effective_from' => __('attendance.validation.schedule_overlap')]);
        }

        return EmployeeScheduleAssignment::query()->create([
            'legal_entity_id' => $employee->legal_entity_id,
            'employee_id' => $employee->getKey(),
            'work_schedule_id' => $schedule->getKey(),
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveTo,
            'source' => 'employee',
            'reason' => $reason,
            'assigned_by' => $actor->getKey(),
        ]);
    }

    /** @param array<string, mixed> $payload
     * @return array{event: AttendanceEvent, record: AttendanceRecord, duplicate: bool}
     */
    public function ingest(User $actor, Employee $employee, AttendanceSource $source, array $payload): array
    {
        if ((int) $employee->legal_entity_id !== (int) $source->legal_entity_id) {
            throw ValidationException::withMessages(['source_public_id' => __('attendance.validation.cross_entity_source')]);
        }

        $canonical = $this->adapterFor($source)->normalize($source, $payload);
        $idempotencyHash = hash('sha256', $source->getKey().'|'.trim((string) $canonical['external_event_id']));
        $existing = AttendanceEvent::query()->where('idempotency_hash', $idempotencyHash)->first();
        if ($existing instanceof AttendanceEvent) {
            $record = $existing->records()->where('is_current', true)->latest('normalization_version')->first();
            if (! $record instanceof AttendanceRecord) {
                $record = $this->normalize($actor, $employee, $existing);
            }

            return ['event' => $existing, 'record' => $record, 'duplicate' => true];
        }

        return DB::transaction(function () use ($actor, $employee, $source, $canonical, $idempotencyHash): array {
            $receivedAt = CarbonImmutable::instance(now())->utc();
            $entity = $employee->legalEntity;
            $timezone = $entity instanceof LegalEntity ? (string) $entity->timezone : (string) config('app.timezone');
            $occurredAt = CarbonImmutable::parse((string) $canonical['occurred_at'], $timezone)->utc();
            $deviceAt = empty($canonical['device_recorded_at'])
                ? null
                : CarbonImmutable::parse((string) $canonical['device_recorded_at'], $timezone)->utc();
            $anomalies = $this->anomalies($source, $canonical, $occurredAt, $deviceAt, $receivedAt);
            $status = $anomalies === [] ? AttendanceEventStatus::Validated : AttendanceEventStatus::Anomalous;
            $payloadHash = hash_hmac('sha256', json_encode($canonical, JSON_THROW_ON_ERROR), (string) config('app.key'));

            $event = AttendanceEvent::query()->create([
                'legal_entity_id' => $employee->legal_entity_id,
                'employee_id' => $employee->getKey(),
                'attendance_source_id' => $source->getKey(),
                'external_event_id' => trim((string) $canonical['external_event_id']),
                'idempotency_hash' => $idempotencyHash,
                'event_type' => $canonical['event_type'],
                'occurred_at' => $occurredAt,
                'device_recorded_at' => $deviceAt,
                'received_at' => $receivedAt,
                'latitude' => $canonical['latitude'],
                'longitude' => $canonical['longitude'],
                'gps_accuracy_meters' => $canonical['gps_accuracy_meters'],
                'selfie_document_id' => $canonical['selfie_document_id'],
                'activity' => $canonical['activity'],
                'destination' => $canonical['destination'],
                'notes' => $canonical['notes'],
                'device_info' => $canonical['device_info'],
                'was_offline' => $canonical['was_offline'],
                'status' => $status,
                'anomaly_codes' => $anomalies,
                'payload_hash' => $payloadHash,
                'payroll_eligibility' => $anomalies === [] ? 'pending_review' : 'blocked',
                'created_by' => $actor->getKey(),
            ]);

            $record = $this->normalize($actor, $employee, $event);
            activity('attendance')->causedBy($actor)->performedOn($event)
                ->event('attendance_event_received')
                ->withProperties([
                    'employee_public_id' => $employee->public_id,
                    'event_public_id' => $event->public_id,
                    'source_type' => $source->sourceType()->value,
                    'status' => $status->value,
                    'anomaly_codes' => $anomalies,
                ])->log('Immutable attendance event received and normalized.');

            return ['event' => $event, 'record' => $record, 'duplicate' => false];
        });
    }

    /** @param array<string, mixed> $data */
    public function submitCorrection(User $actor, Employee $employee, AttendanceRecord $record, array $data, ?int $evidenceDocumentId = null): AttendanceCorrection
    {
        if ((int) $actor->employee_id !== (int) $employee->getKey()
            || (int) $record->employee_id !== (int) $employee->getKey()
            || ! $record->is_current) {
            abort(404);
        }

        $old = $this->recordSnapshot($record);
        $entity = $employee->legalEntity;
        $timezone = $entity instanceof LegalEntity ? (string) $entity->timezone : (string) config('app.timezone');
        $checkIn = empty($data['proposed_check_in_at'])
            ? $record->checkInAt()
            : CarbonImmutable::parse((string) $data['proposed_check_in_at'], $timezone)->utc();
        $checkOut = empty($data['proposed_check_out_at'])
            ? $record->checkOutAt()
            : CarbonImmutable::parse((string) $data['proposed_check_out_at'], $timezone)->utc();
        $workDate = $record->workDate()->toDateString();
        if (($checkIn && $checkIn->setTimezone($timezone)->toDateString() !== $workDate)
            || ($checkOut && $checkOut->setTimezone($timezone)->toDateString() !== $workDate)) {
            throw ValidationException::withMessages(['proposed_check_in_at' => __('attendance.validation.correction_work_date')]);
        }
        if ($checkIn && $checkOut && $checkOut->lessThanOrEqualTo($checkIn)) {
            throw ValidationException::withMessages(['proposed_check_out_at' => __('attendance.validation.correction_time_order')]);
        }
        $proposed = [
            'check_in_at' => $checkIn?->format(DateTimeInterface::ATOM),
            'check_out_at' => $checkOut?->format(DateTimeInterface::ATOM),
        ];

        return AttendanceCorrection::query()->create([
            'legal_entity_id' => $employee->legal_entity_id,
            'employee_id' => $employee->getKey(),
            'attendance_record_id' => $record->getKey(),
            'requested_by' => $actor->getKey(),
            'type' => $data['type'],
            'status' => AttendanceCorrectionStatus::PendingManager,
            'reason' => $data['reason'],
            'old_values' => $old,
            'proposed_values' => $proposed,
            'snapshot_fingerprint' => $this->fingerprint($old),
            'evidence_document_id' => $evidenceDocumentId,
            'submitted_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $data */
    public function managerReview(User $actor, AttendanceCorrection $correction, array $data): AttendanceCorrection
    {
        if ($correction->correctionStatus() !== AttendanceCorrectionStatus::PendingManager) {
            throw ValidationException::withMessages(['decision' => __('attendance.validation.correction_not_pending')]);
        }

        $actorEmployee = $actor->employee;
        $employee = $correction->employee;
        $assignment = $employee?->currentEmployment;
        if (! $actorEmployee instanceof Employee || ! $employee instanceof Employee
            || (int) $assignment?->manager_employee_id !== (int) $actorEmployee->getKey()) {
            abort(403);
        }

        $approved = $data['decision'] === 'approve';
        $correction->update([
            'status' => $approved ? AttendanceCorrectionStatus::PendingHr : AttendanceCorrectionStatus::Rejected,
            'manager_reviewed_by' => $actor->getKey(),
            'manager_review_notes' => $data['review_notes'],
            'manager_reviewed_at' => now(),
        ]);

        return $correction->refresh();
    }

    /** @param array<string, mixed> $data */
    public function hrReview(User $actor, AttendanceCorrection $correction, array $data): AttendanceCorrection
    {
        if ($correction->correctionStatus() !== AttendanceCorrectionStatus::PendingHr) {
            throw ValidationException::withMessages(['decision' => __('attendance.validation.correction_not_pending')]);
        }
        if (! $this->scope->manages($actor, (int) $correction->legal_entity_id)) {
            abort(404);
        }

        return DB::transaction(function () use ($actor, $correction, $data): AttendanceCorrection {
            if ($data['decision'] === 'reject') {
                $correction->update([
                    'status' => AttendanceCorrectionStatus::Rejected,
                    'hr_reviewed_by' => $actor->getKey(),
                    'hr_review_notes' => $data['review_notes'],
                    'hr_reviewed_at' => now(),
                ]);

                return $correction->refresh();
            }

            $record = AttendanceRecord::query()->lockForUpdate()->findOrFail($correction->attendance_record_id);
            if (! $record->is_current || ! hash_equals($correction->snapshot_fingerprint, $this->fingerprint($this->recordSnapshot($record)))) {
                throw ValidationException::withMessages(['decision' => __('attendance.validation.stale_correction')]);
            }

            $proposed = $correction->proposedValues();
            $replacement = $this->versionFromCorrection($actor, $record, $proposed);
            $correction->update([
                'status' => AttendanceCorrectionStatus::Approved,
                'hr_reviewed_by' => $actor->getKey(),
                'hr_review_notes' => $data['review_notes'],
                'hr_reviewed_at' => now(),
                'applied_record_id' => $replacement->getKey(),
                'applied_at' => now(),
            ]);

            activity('attendance')->causedBy($actor)->performedOn($correction)
                ->event('attendance_correction_approved')
                ->withProperties([
                    'correction_public_id' => $correction->public_id,
                    'old_record_public_id' => $record->public_id,
                    'new_record_public_id' => $replacement->public_id,
                ])->log('Attendance correction approved; a new normalized version was created.');

            return $correction->refresh();
        });
    }

    public function cancelCorrection(User $actor, AttendanceCorrection $correction): void
    {
        if ((int) $correction->requested_by !== (int) $actor->getKey()
            || $correction->correctionStatus() !== AttendanceCorrectionStatus::PendingManager) {
            abort(403);
        }
        $correction->update(['status' => AttendanceCorrectionStatus::Cancelled, 'cancelled_at' => now()]);
    }

    private function normalize(User $actor, Employee $employee, AttendanceEvent $trigger): AttendanceRecord
    {
        $entity = $employee->legalEntity;
        $timezone = $entity instanceof LegalEntity ? (string) $entity->timezone : (string) config('app.timezone');
        $localOccurred = $trigger->occurredAt()->setTimezone($timezone);
        $workDate = $localOccurred->toDateString();
        $localStart = CarbonImmutable::parse($workDate.' 00:00:00', $timezone);
        $localEnd = $localStart->endOfDay();
        $events = AttendanceEvent::query()
            ->where('employee_id', $employee->getKey())
            ->whereBetween('occurred_at', [$localStart->utc(), $localEnd->utc()])
            ->oldest('occurred_at')->get();
        $assignment = EmployeeScheduleAssignment::query()
            ->where('employee_id', $employee->getKey())->effectiveOn($workDate)
            ->with('schedule.days')->latest('effective_from')->first();
        $schedule = $assignment instanceof EmployeeScheduleAssignment
            ? $assignment->schedule
            : $this->fallbackSchedule($employee, $workDate);
        $day = $schedule instanceof WorkSchedule
            ? $schedule->days->firstWhere('day_of_week', $localOccurred->dayOfWeekIso)
            : null;
        $scheduledStart = null;
        $scheduledEnd = null;
        if ($day?->is_working_day && $day->start_time && $day->end_time) {
            $scheduledStart = CarbonImmutable::parse($workDate.' '.$day->start_time, $timezone)->utc();
            $scheduledEnd = CarbonImmutable::parse($workDate.' '.$day->end_time, $timezone)->utc();
        }

        $checkInEvent = $events->first(fn (AttendanceEvent $event) => $event->eventType() === AttendanceEventType::CheckIn);
        $checkOutEvent = $events->filter(fn (AttendanceEvent $event) => $event->eventType() === AttendanceEventType::CheckOut)->last();
        $checkIn = $checkInEvent instanceof AttendanceEvent ? $checkInEvent->occurredAt() : null;
        $checkOut = $checkOutEvent instanceof AttendanceEvent ? $checkOutEvent->occurredAt() : null;
        $anomalous = $events->contains(fn (AttendanceEvent $event) => $event->eventStatus() === AttendanceEventStatus::Anomalous);
        $holiday = Holiday::query()->where('legal_entity_id', $employee->legal_entity_id)
            ->whereDate('holiday_date', $workDate)->where('status', 'active')
            ->where(fn (Builder $query) => $query->whereNull('branch_id')
                ->when($employee->currentEmployment?->branch_id, fn (Builder $branchQuery, mixed $branchId) => $branchQuery->orWhere('branch_id', $branchId)))
            ->exists();
        $status = $holiday ? 'holiday_attendance' : ($checkIn && $checkOut ? 'present' : 'incomplete');
        if ($anomalous) {
            $status = 'anomalous';
        }
        $lateGrace = $schedule instanceof WorkSchedule ? (int) $schedule->late_grace_minutes : 0;
        $earlyGrace = $schedule instanceof WorkSchedule ? (int) $schedule->early_leave_grace_minutes : 0;
        $lateMinutes = $checkIn && $scheduledStart
            ? max(0, $scheduledStart->addMinutes($lateGrace)->diffInMinutes($checkIn, false)) : 0;
        $earlyMinutes = $checkOut && $scheduledEnd
            ? max(0, $checkOut->diffInMinutes($scheduledEnd->subMinutes($earlyGrace), false)) : 0;
        $workedMinutes = $checkIn && $checkOut ? max(0, $checkIn->diffInMinutes($checkOut, false)) : 0;
        $current = AttendanceRecord::query()->where('employee_id', $employee->getKey())
            ->whereDate('work_date', $workDate)->where('is_current', true)->lockForUpdate()->first();
        $version = ($current instanceof AttendanceRecord ? (int) $current->normalization_version : 0) + 1;
        $current?->update(['is_current' => false]);

        $record = AttendanceRecord::query()->create([
            'legal_entity_id' => $employee->legal_entity_id,
            'employee_id' => $employee->getKey(),
            'employee_schedule_assignment_id' => $assignment?->getKey(),
            'work_schedule_id' => $schedule?->getKey(),
            'work_date' => $workDate,
            'scheduled_start_at' => $scheduledStart,
            'scheduled_end_at' => $scheduledEnd,
            'check_in_at' => $checkIn,
            'check_out_at' => $checkOut,
            'worked_minutes' => $workedMinutes,
            'late_minutes' => $lateMinutes,
            'early_leave_minutes' => $earlyMinutes,
            'overtime_minutes' => 0,
            'status' => $status,
            'payroll_eligibility' => $status === 'present' ? 'pending_review' : 'blocked',
            'normalization_version' => $version,
            'supersedes_id' => $current?->getKey(),
            'is_current' => true,
            'normalized_by' => $actor->getKey(),
            'normalized_at' => now(),
        ]);
        $record->events()->sync($events->modelKeys());

        return $record;
    }

    /** @param array<string, mixed> $canonical
     * @return list<string>
     */
    private function anomalies(
        AttendanceSource $source,
        array $canonical,
        CarbonImmutable $occurredAt,
        ?CarbonImmutable $deviceAt,
        CarbonImmutable $receivedAt,
    ): array {
        $rules = $source->validationRules();
        $requiresGps = (bool) ($rules['requires_gps'] ?? in_array($source->sourceType(), [AttendanceSourceType::MobileGps, AttendanceSourceType::OfflineMobile], true));
        $anomalies = [];
        if ($requiresGps && ($canonical['latitude'] === null || $canonical['longitude'] === null)) {
            $anomalies[] = 'gps_missing';
        }
        if ((bool) ($rules['requires_selfie'] ?? false) && $canonical['selfie_document_id'] === null) {
            $anomalies[] = 'selfie_missing';
        }
        $accuracy = $canonical['gps_accuracy_meters'];
        if ($accuracy !== null && (int) $accuracy > (int) ($rules['max_gps_accuracy_meters'] ?? 150)) {
            $anomalies[] = 'gps_accuracy_low';
        }
        if ((bool) $canonical['was_offline']) {
            if ($deviceAt === null) {
                $anomalies[] = 'offline_device_time_missing';
            }
            if (abs($occurredAt->diffInMinutes($receivedAt, false)) > (int) ($rules['max_offline_delay_minutes'] ?? 720)) {
                $anomalies[] = 'offline_sync_delayed';
            }
        }
        if ($deviceAt && abs($deviceAt->diffInMinutes($occurredAt, false)) > 5) {
            $anomalies[] = 'device_clock_mismatch';
        }

        return array_values(array_unique($anomalies));
    }

    /** @return array<string, mixed> */
    private function recordSnapshot(AttendanceRecord $record): array
    {
        return [
            'record_public_id' => $record->public_id,
            'normalization_version' => $record->normalization_version,
            'work_date' => $record->workDate()->toDateString(),
            'check_in_at' => $record->checkInAt()?->format(DateTimeInterface::ATOM),
            'check_out_at' => $record->checkOutAt()?->format(DateTimeInterface::ATOM),
        ];
    }

    /** @param array<string, mixed> $values */
    private function fingerprint(array $values): string
    {
        return hash_hmac('sha256', json_encode($values, JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    /** @param array<string, mixed> $proposed */
    private function versionFromCorrection(User $actor, AttendanceRecord $record, array $proposed): AttendanceRecord
    {
        $checkIn = empty($proposed['check_in_at']) ? null : CarbonImmutable::parse((string) $proposed['check_in_at'])->utc();
        $checkOut = empty($proposed['check_out_at']) ? null : CarbonImmutable::parse((string) $proposed['check_out_at'])->utc();
        $schedule = $record->work_schedule_id
            ? WorkSchedule::query()->find($record->work_schedule_id)
            : null;
        $lateGrace = $schedule instanceof WorkSchedule ? (int) $schedule->late_grace_minutes : 0;
        $earlyGrace = $schedule instanceof WorkSchedule ? (int) $schedule->early_leave_grace_minutes : 0;
        $scheduledStart = $record->scheduledStartAt();
        $scheduledEnd = $record->scheduledEndAt();
        $record->update(['is_current' => false]);

        $replacement = AttendanceRecord::query()->create([
            'legal_entity_id' => $record->legal_entity_id,
            'employee_id' => $record->employee_id,
            'employee_schedule_assignment_id' => $record->employee_schedule_assignment_id,
            'work_schedule_id' => $record->work_schedule_id,
            'work_date' => $record->work_date,
            'scheduled_start_at' => $scheduledStart,
            'scheduled_end_at' => $scheduledEnd,
            'check_in_at' => $checkIn,
            'check_out_at' => $checkOut,
            'worked_minutes' => $checkIn && $checkOut ? max(0, $checkIn->diffInMinutes($checkOut, false)) : 0,
            'late_minutes' => $checkIn && $scheduledStart
                ? max(0, $scheduledStart->addMinutes($lateGrace)->diffInMinutes($checkIn, false)) : 0,
            'early_leave_minutes' => $checkOut && $scheduledEnd
                ? max(0, $checkOut->diffInMinutes($scheduledEnd->subMinutes($earlyGrace), false)) : 0,
            'overtime_minutes' => $record->overtime_minutes,
            'status' => $checkIn && $checkOut ? 'present' : 'incomplete',
            'payroll_eligibility' => 'pending_review',
            'normalization_version' => $record->normalization_version + 1,
            'supersedes_id' => $record->getKey(),
            'is_current' => true,
            'normalized_by' => $actor->getKey(),
            'normalized_at' => now(),
        ]);
        $replacement->events()->sync($record->events()->pluck('attendance_events.id')->all());

        return $replacement;
    }

    private function adapterFor(AttendanceSource $source): AttendanceSourceAdapter
    {
        return match ($source->adapter) {
            'web_gps_v1', 'offline_mobile_v1', 'x100c_csv_v1' => new WebGpsSourceAdapter,
            default => throw ValidationException::withMessages(['source_public_id' => __('attendance.validation.unsupported_adapter')]),
        };
    }

    private function fallbackSchedule(Employee $employee, string $workDate): ?WorkSchedule
    {
        $employment = $employee->currentEmployment;
        $base = WorkSchedule::query()->where('legal_entity_id', $employee->legal_entity_id)
            ->where('status', 'active')->effectiveOn($workDate)->with('days');
        if ($employment?->department_id) {
            $department = (clone $base)->where('department_id', $employment->department_id)
                ->latest('effective_from')->first();
            if ($department instanceof WorkSchedule) {
                return $department;
            }
        }
        if ($employment?->branch_id) {
            $branch = (clone $base)->whereNull('department_id')->where('branch_id', $employment->branch_id)
                ->latest('effective_from')->first();
            if ($branch instanceof WorkSchedule) {
                return $branch;
            }
        }

        return $base->whereNull('branch_id')->whereNull('department_id')->latest('effective_from')->first();
    }

    private function assertManaged(User $actor, LegalEntity $entity): void
    {
        if (! $actor->can('attendance.manage') || ! $this->scope->manages($actor, (int) $entity->getKey())) {
            abort(403);
        }
    }
}
