<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLegalEntity;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalDelegation extends Model
{
    use BelongsToLegalEntity, HasPublicId;

    protected $fillable = [
        'legal_entity_id', 'delegator_user_id', 'delegate_user_id', 'subject_type', 'effective_from',
        'effective_to', 'reason', 'status', 'granted_by', 'revoked_by', 'revoked_at',
    ];

    protected $hidden = ['reason'];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date', 'effective_to' => 'date', 'reason' => 'encrypted',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    /** @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeEffectiveOn(Builder $query, string $date): Builder
    {
        return $query->where('status', 'active')->whereDate('effective_from', '<=', $date)
            ->whereDate('effective_to', '>=', $date);
    }

    /** @return BelongsTo<User, $this> */
    public function delegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_user_id');
    }
}
