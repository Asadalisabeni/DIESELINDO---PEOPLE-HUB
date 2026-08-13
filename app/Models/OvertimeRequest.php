<?php

namespace App\Models;

use App\Casts\UtcImmutableDateTime;
use App\Enums\OvertimeDayType;
use App\Enums\OvertimeRequestStatus;
use App\Enums\OvertimeRequestType;
use App\Models\Concerns\BelongsToLegalEntity;
use App\Models\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OvertimeRequest extends Model
{
    use BelongsToLegalEntity, HasPublicId;

    protected $fillable = [
        'legal_entity_id', 'employee_id', 'overtime_rule_id', 'requested_by', 'request_type',
        'day_type_snapshot', 'work_date', 'planned_start_at', 'planned_end_at', 'planned_minutes',
        'approved_minutes', 'attendance_record_id', 'actual_start_at', 'actual_end_at', 'actual_minutes',
        'payable_minutes', 'weighted_minutes_hundredths', 'meal_eligible', 'meal_allowance_idr',
        'transport_eligible', 'transport_allowance_idr', 'reason', 'work_description', 'validation_note',
        'status', 'approval_instance_id', 'request_fingerprint', 'payroll_period_key', 'submitted_at',
        'approved_at', 'validated_at', 'payroll_eligible_at', 'rejected_at', 'cancelled_at',
    ];

    protected $hidden = ['reason', 'work_description', 'validation_note', 'request_fingerprint'];

    protected function casts(): array
    {
        return [
            'request_type' => OvertimeRequestType::class, 'day_type_snapshot' => OvertimeDayType::class,
            'status' => OvertimeRequestStatus::class, 'work_date' => 'date',
            'planned_start_at' => UtcImmutableDateTime::class, 'planned_end_at' => UtcImmutableDateTime::class,
            'actual_start_at' => UtcImmutableDateTime::class, 'actual_end_at' => UtcImmutableDateTime::class,
            'reason' => 'encrypted', 'work_description' => 'encrypted', 'validation_note' => 'encrypted',
            'meal_eligible' => 'boolean', 'transport_eligible' => 'boolean',
            'submitted_at' => 'immutable_datetime', 'approved_at' => 'immutable_datetime',
            'validated_at' => 'immutable_datetime', 'payroll_eligible_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime',
        ];
    }

    public function requestStatus(): OvertimeRequestStatus
    {
        return OvertimeRequestStatus::from((string) $this->getRawOriginal('status'));
    }

    public function dayType(): OvertimeDayType
    {
        return OvertimeDayType::from((string) $this->getRawOriginal('day_type_snapshot'));
    }

    public function requestType(): OvertimeRequestType
    {
        return OvertimeRequestType::from((string) $this->getRawOriginal('request_type'));
    }

    public function plannedStartAt(): CarbonImmutable
    {
        $value = $this->getAttribute('planned_start_at');
        if (! $value instanceof CarbonImmutable) {
            throw new \LogicException('Overtime planned start is invalid.');
        }

        return $value;
    }

    public function plannedEndAt(): CarbonImmutable
    {
        $value = $this->getAttribute('planned_end_at');
        if (! $value instanceof CarbonImmutable) {
            throw new \LogicException('Overtime planned end is invalid.');
        }

        return $value;
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<OvertimeRule, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(OvertimeRule::class, 'overtime_rule_id');
    }

    /** @return BelongsTo<AttendanceRecord, $this> */
    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    /** @return BelongsTo<ApprovalInstance, $this> */
    public function approvalInstance(): BelongsTo
    {
        return $this->belongsTo(ApprovalInstance::class);
    }

    /** @return HasOne<OvertimeCalculation, $this> */
    public function calculation(): HasOne
    {
        return $this->hasOne(OvertimeCalculation::class);
    }
}
