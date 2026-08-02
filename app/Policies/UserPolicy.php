<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Iam\RoleMatrix;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('iam.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('iam.manage');
    }

    public function update(User $user, User $target): bool
    {
        if (! $user->can('iam.manage')) {
            return false;
        }

        if ($user->hasRole('Super Admin')) {
            return true;
        }

        return $target->getRoleNames()->every(
            fn (string $roleName): bool => in_array($roleName, RoleMatrix::GROUP_HR_DELEGABLE_ROLES, true),
        );
    }
}
