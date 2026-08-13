<?php

namespace App\Models;

use App\Enums\ApprovalInstanceStatus;
use App\Models\Concerns\BelongsToLegalEntity;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalInstance extends Model
{
    use BelongsToLegalEntity, HasPublicId;

    protected $fillable = [
        'legal_entity_id', 'approval_definition_id', 'subject_type', 'subject_public_id',
        'subject_snapshot', 'snapshot_checksum', 'requested_by', 'status', 'current_step_order',
        'correlation_id', 'submitted_at', 'completed_at', 'cancelled_at',
    ];

    protected $hidden = ['subject_snapshot', 'snapshot_checksum'];

    protected function casts(): array
    {
        return [
            'subject_snapshot' => 'encrypted:array', 'status' => ApprovalInstanceStatus::class,
            'current_step_order' => 'integer', 'submitted_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime',
        ];
    }

    public function instanceStatus(): ApprovalInstanceStatus
    {
        return ApprovalInstanceStatus::from((string) $this->getRawOriginal('status'));
    }

    /** @return BelongsTo<ApprovalDefinition, $this> */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(ApprovalDefinition::class, 'approval_definition_id');
    }

    /** @return HasMany<ApprovalInstanceStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalInstanceStep::class)->orderBy('step_order');
    }

    /** @return HasMany<ApprovalAction, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class)->orderBy('acted_at');
    }
}
