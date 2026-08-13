<?php

namespace App\Models;

use App\Casts\UtcImmutableDateTime;
use App\Models\Concerns\BelongsToLegalEntity;
use App\Models\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceRecord extends Model
{
    use BelongsToLegalEntity, HasPublicId;

    protected $fillable = [
        'legal_entity_id', 'employee_id', 'employee_schedule_assignment_id', 'work_schedule_id', 'work_date',
        'scheduled_start_at', 'scheduled_end_at', 'check_in_at', 'check_out_at', 'worked_minutes',
        'late_minutes', 'early_leave_minutes', 'overtime_minutes', 'status', 'payroll_eligibility',
        'normalization_version', 'supersedes_id', 'is_current', 'normalized_by', 'normalized_at',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'scheduled_start_at' => UtcImmutableDateTime::class,
            'scheduled_end_at' => UtcImmutableDateTime::class,
            'check_in_at' => UtcImmutableDateTime::class,
            'check_out_at' => UtcImmutableDateTime::class,
            'is_current' => 'boolean',
            'normalized_at' => UtcImmutableDateTime::class,
            'worked_minutes' => 'integer',
            'late_minutes' => 'integer',
            'early_leave_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'normalization_version' => 'integer',
        ];
    }

    public function workDate(): CarbonImmutable
    {
        $value = $this->getAttribute('work_date');
        if (! $value instanceof CarbonInterface) {
            throw new \LogicException('Attendance work date is invalid.');
        }

        return CarbonImmutable::instance($value);
    }

    public function scheduledStartAt(): ?CarbonImmutable
    {
        return $this->utcValue('scheduled_start_at');
    }

    public function scheduledEndAt(): ?CarbonImmutable
    {
        return $this->utcValue('scheduled_end_at');
    }

    public function checkInAt(): ?CarbonImmutable
    {
        return $this->utcValue('check_in_at');
    }

    public function checkOutAt(): ?CarbonImmutable
    {
        return $this->utcValue('check_out_at');
    }

    private function utcValue(string $attribute): ?CarbonImmutable
    {
        $value = $this->getAttribute($attribute);

        return $value instanceof CarbonImmutable ? $value : null;
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<EmployeeScheduleAssignment, $this> */
    public function scheduleAssignment(): BelongsTo
    {
        return $this->belongsTo(EmployeeScheduleAssignment::class, 'employee_schedule_assignment_id');
    }

    /** @return BelongsTo<WorkSchedule, $this> */
    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
    }

    /** @return BelongsToMany<AttendanceEvent, $this> */
    public function events(): BelongsToMany
    {
        return $this->belongsToMany(AttendanceEvent::class, 'attendance_record_events');
    }

    /** @return HasMany<AttendanceCorrection, $this> */
    public function corrections(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class);
    }
}
