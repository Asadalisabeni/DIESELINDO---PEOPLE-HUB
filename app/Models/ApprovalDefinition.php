<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalDefinition extends Model
{
    use HasPublicId;

    protected $fillable = [
        'legal_entity_id', 'key', 'subject_type', 'version', 'risk_class', 'effective_from',
        'effective_to', 'status', 'reminder_after_hours', 'escalation_after_hours', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer', 'effective_from' => 'date', 'effective_to' => 'date',
            'reminder_after_hours' => 'integer', 'escalation_after_hours' => 'integer',
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

    /** @return HasMany<ApprovalStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class)->orderBy('step_order');
    }

    /** @return BelongsTo<LegalEntity, $this> */
    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }
}
