<?php

namespace App\Models\Concerns;

use App\Models\LegalEntity;
use App\Models\User;
use App\Services\Organization\LegalEntityScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToLegalEntity
{
    /** @return BelongsTo<LegalEntity, $this> */
    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    /** @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereIn(
            $query->qualifyColumn('legal_entity_id'),
            app(LegalEntityScope::class)->idsFor($user),
        );
    }
}
