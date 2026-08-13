<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLegalEntity;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeavePolicy extends Model
{
    use BelongsToLegalEntity, HasPublicId;

    protected $fillable = [
        'legal_entity_id', 'leave_type_id', 'version', 'eligibility_months', 'entitlement_quantity',
        'validity_months', 'carry_forward_enabled', 'carry_forward_limit', 'minimum_notice_days',
        'maximum_request_days', 'effective_from', 'effective_to', 'status', 'created_by', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer', 'eligibility_months' => 'integer', 'validity_months' => 'integer',
            'entitlement_quantity' => 'decimal:2', 'carry_forward_limit' => 'decimal:2',
            'maximum_request_days' => 'decimal:2', 'carry_forward_enabled' => 'boolean',
            'minimum_notice_days' => 'integer', 'effective_from' => 'date', 'effective_to' => 'date',
            'approved_at' => 'immutable_datetime',
        ];
    }

    /** @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeEffectiveOn(Builder $query, string $date): Builder
    {
        return $query->where('status', 'active')->whereDate('effective_from', '<=', $date)
            ->where(fn (Builder $period) => $period->whereNull('effective_to')->orWhereDate('effective_to', '>', $date));
    }

    /** @return BelongsTo<LeaveType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }
}
