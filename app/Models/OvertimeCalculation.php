<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLegalEntity;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class OvertimeCalculation extends Model
{
    use BelongsToLegalEntity, HasPublicId;

    protected $fillable = [
        'legal_entity_id', 'overtime_request_id', 'overtime_rule_id', 'attendance_record_id',
        'planned_minutes', 'approved_minutes', 'actual_minutes', 'payable_minutes',
        'weighted_minutes_hundredths', 'meal_eligible', 'meal_allowance_idr',
        'transport_eligible', 'transport_allowance_idr', 'payroll_eligible',
        'rule_snapshot', 'calculation_trace', 'rule_checksum', 'calculated_by', 'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'meal_eligible' => 'boolean', 'transport_eligible' => 'boolean',
            'payroll_eligible' => 'boolean', 'rule_snapshot' => 'array', 'calculation_trace' => 'array',
            'calculated_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Overtime calculations are immutable.'));
        static::deleting(fn () => throw new LogicException('Overtime calculations are immutable.'));
    }

    /** @return BelongsTo<OvertimeRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(OvertimeRequest::class, 'overtime_request_id');
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
}
