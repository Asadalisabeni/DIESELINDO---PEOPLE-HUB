<?php

namespace App\Policies;

use App\Models\LegalEntity;
use App\Models\User;
use App\Services\Organization\LegalEntityScope;

class LegalEntityPolicy
{
    public function __construct(private readonly LegalEntityScope $scope) {}

    public function viewAny(User $user): bool
    {
        return $user->can('organization.view') || $user->can('organization.manage');
    }

    public function view(User $user, LegalEntity $entity): bool
    {
        return $this->viewAny($user) && $this->scope->contains($user, (int) $entity->getKey());
    }

    public function create(User $user): bool
    {
        return $user->can('organization.manage') && $user->can('entity-access.manage');
    }

    public function update(User $user, LegalEntity $entity): bool
    {
        return $user->can('organization.manage') && $this->scope->manages($user, (int) $entity->getKey());
    }
}
