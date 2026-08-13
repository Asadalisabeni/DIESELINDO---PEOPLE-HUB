<?php

namespace App\Models;

use App\Enums\ApprovalActionType;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ApprovalAction extends Model
{
    use HasPublicId;

    protected $fillable = [
        'approval_instance_id', 'approval_instance_step_id', 'actor_user_id', 'acting_for_user_id',
        'action', 'note', 'attachment_document_id', 'idempotency_hash', 'acted_at',
    ];

    protected $hidden = ['note', 'idempotency_hash'];

    protected function casts(): array
    {
        return ['action' => ApprovalActionType::class, 'note' => 'encrypted', 'acted_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(static fn (): never => throw new LogicException('Approval actions are immutable.'));
        static::deleting(static fn (): never => throw new LogicException('Approval actions are immutable.'));
    }

    /** @return BelongsTo<ApprovalInstance, $this> */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(ApprovalInstance::class, 'approval_instance_id');
    }
}
