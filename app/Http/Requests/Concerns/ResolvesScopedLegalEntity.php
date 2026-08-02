<?php

namespace App\Http\Requests\Concerns;

use App\Models\LegalEntity;
use App\Services\Organization\LegalEntityScope;

trait ResolvesScopedLegalEntity
{
    private ?LegalEntity $resolvedLegalEntity = null;

    protected function resolveLegalEntity(string $input = 'legal_entity_public_id'): ?LegalEntity
    {
        if ($this->resolvedLegalEntity) {
            return $this->resolvedLegalEntity;
        }

        $user = $this->user();
        $publicId = $this->route('legalEntity') ?? $this->input($input);

        if (! $user || ! is_string($publicId)) {
            return null;
        }

        $this->resolvedLegalEntity = LegalEntity::query()
            ->whereIn('id', app(LegalEntityScope::class)->idsFor($user))
            ->where('public_id', $publicId)
            ->first();

        return $this->resolvedLegalEntity;
    }

    public function scopedLegalEntity(): LegalEntity
    {
        return $this->resolvedLegalEntity ?? abort(404);
    }
}
