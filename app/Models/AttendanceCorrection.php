<?php

namespace App\Models;

use App\Enums\AttendanceCorrectionStatus;
use App\Models\Concerns\BelongsToLegalEntity;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCorrection extends Model
{
    use BelongsToLegalEntity, HasPublicId;

    protected $fillable = [
        'legal_entity_id', 'employee_id', 'attendance_record_id', 'requested_by', 'type', 'status',
        'reason', 'old_values', 'proposed_values', 'snapshot_fingerprint', 'evidence_document_id',
        'manager_reviewed_by', 'manager_review_notes', 'manager_reviewed_at', 'hr_reviewed_by',
        'hr_review_notes', 'hr_reviewed_at', 'applied_record_id', 'submitted_at', 'applied_at', 'cancelled_at',
    ];

    protected $hidden = ['snapshot_fingerprint'];

    protected function casts(): array
    {
        return [
            'status' => AttendanceCorrectionStatus::class,
            'reason' => 'encrypted',
            'old_values' => 'encrypted:array',
            'proposed_values' => 'encrypted:array',
            'manager_review_notes' => 'encrypted',
            'hr_review_notes' => 'encrypted',
            'manager_reviewed_at' => 'immutable_datetime',
            'hr_reviewed_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
            'applied_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    public function correctionStatus(): AttendanceCorrectionStatus
    {
        return AttendanceCorrectionStatus::from((string) $this->getRawOriginal('status'));
    }

    /** @return array<string, mixed> */
    public function oldValues(): array
    {
        $value = $this->getAttribute('old_values');

        return is_array($value) ? $value : [];
    }

    /** @return array<string, mixed> */
    public function proposedValues(): array
    {
        $value = $this->getAttribute('proposed_values');

        return is_array($value) ? $value : [];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<AttendanceRecord, $this> */
    public function record(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class, 'attendance_record_id');
    }

    /** @return BelongsTo<AttendanceRecord, $this> */
    public function appliedRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class, 'applied_record_id');
    }
}
