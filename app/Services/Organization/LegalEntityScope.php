<?php

namespace App\Services\Organization;

use App\Models\User;
use App\Models\UserLegalEntityAccess;

class LegalEntityScope
{
    /** @var array<string, list<int>> */
    private array $resolved = [];

    /** @return list<int> */
    public function idsFor(User $user, ?string $date = null): array
    {
        $effectiveDate = $date ?? now()->toDateString();
        $key = $user->getKey().'|'.$effectiveDate;

        if (! array_key_exists($key, $this->resolved)) {
            $ids = UserLegalEntityAccess::query()
                ->where('user_id', $user->getKey())
                ->effectiveOn($effectiveDate)
                ->pluck('legal_entity_id')
                ->all();
            $this->resolved[$key] = array_values(array_map(static fn (mixed $id): int => (int) $id, $ids));
        }

        return $this->resolved[$key];
    }

    public function contains(User $user, int $legalEntityId, ?string $date = null): bool
    {
        return in_array($legalEntityId, $this->idsFor($user, $date), true);
    }

    public function manages(User $user, int $legalEntityId, ?string $date = null): bool
    {
        return UserLegalEntityAccess::query()
            ->where('user_id', $user->getKey())
            ->where('legal_entity_id', $legalEntityId)
            ->where('access_level', 'manage')
            ->effectiveOn($date ?? now()->toDateString())
            ->exists();
    }

    public function forget(User $user): void
    {
        foreach (array_keys($this->resolved) as $key) {
            if (str_starts_with($key, $user->getKey().'|')) {
                unset($this->resolved[$key]);
            }
        }
    }
}
