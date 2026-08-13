<?php

namespace App\Models;

use App\Enums\LeaveRequestStatus;
use App\Models\Concerns\BelongsToLegalEntity;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveRequest extends Model
{
    use BelongsToLegalEntity, HasPublicId;

    protected $fillable = [
        'legal_entity_id', 'employee_id', 'leave_type_id', 'leave_policy_id', 'requested_by',
        'start_date', 'end_date', 'total_days', 'reason', 'evidence_document_id', 'is_paid_snapshot',
        'requires_balance_snapshot', 'status', 'approval_instance_id', 'request_fingerprint',
        'submitted_at', 'approved_at', 'rejected_at', 'cancelled_at',
    ];

    protected $hidden = ['reason', 'request_fingerprint'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date', 'end_date' => 'date', 'total_days' => 'decimal:2',
            'reason' => 'encrypted', 'is_paid_snapshot' => 'boolean', 'requires_balance_snapshot' => 'boolean',
            'status' => LeaveRequestStatus::class, 'submitted_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime', 'rejected_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    public function requestStatus(): LeaveRequestStatus
    {
        return LeaveRequestStatus::from((string) $this->getRawOriginal('status'));
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

    /** @return BelongsTo<LeaveType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    /** @return BelongsTo<LeavePolicy, $this> */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(LeavePolicy::class, 'leave_policy_id');
    }

    /** @return BelongsTo<ApprovalInstance, $this> */
    public function approvalInstance(): BelongsTo
    {
        return $this->belongsTo(ApprovalInstance::class);
    }

    /** @return BelongsTo<EmployeeDocument, $this> */
    public function evidenceDocument(): BelongsTo
    {
        return $this->belongsTo(EmployeeDocument::class, 'evidence_document_id');
    }

    /** @return HasMany<LeaveLedgerEntry, $this> */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LeaveLedgerEntry::class);
    }
}
