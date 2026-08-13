<?php

namespace App\Models;

use App\Enums\ApprovalStepStatus;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalInstanceStep extends Model
{
    use HasPublicId;

    protected $fillable = [
        'approval_instance_id', 'step_order', 'name', 'resolver_type', 'resolver_snapshot',
        'assigned_approver_user_id', 'delegated_from_user_id', 'required_permission', 'status',
        'due_at', 'escalated_at', 'completed_at',
    ];

    protected $hidden = ['resolver_snapshot'];

    protected function casts(): array
    {
        return [
            'step_order' => 'integer', 'resolver_snapshot' => 'encrypted:array',
            'status' => ApprovalStepStatus::class, 'due_at' => 'immutable_datetime',
            'escalated_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime',
        ];
    }

    public function stepStatus(): ApprovalStepStatus
    {
        return ApprovalStepStatus::from((string) $this->getRawOriginal('status'));
    }

    /** @return BelongsTo<ApprovalInstance, $this> */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(ApprovalInstance::class, 'approval_instance_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_approver_user_id');
    }
}
