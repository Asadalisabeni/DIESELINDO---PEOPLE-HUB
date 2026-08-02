<?php

namespace App\Policies;

use App\Models\EmployeeProfileChangeRequest;
use App\Models\User;
use App\Services\Organization\LegalEntityScope;

class EmployeeProfileChangeRequestPolicy
{
    public function __construct(private readonly LegalEntityScope $scope) {}

    public function view(User $user, EmployeeProfileChangeRequest $changeRequest): bool
    {
        if ((int) $changeRequest->requested_by === (int) $user->getKey()) {
            return $user->can('ess.access');
        }

        return $this->review($user, $changeRequest);
    }

    public function review(User $user, EmployeeProfileChangeRequest $changeRequest): bool
    {
        return $user->can('ess.profile-change.review')
            && $this->scope->manages($user, (int) $changeRequest->legal_entity_id);
    }

    public function cancel(User $user, EmployeeProfileChangeRequest $changeRequest): bool
    {
        return $user->can('ess.profile-change.request')
            && (int) $changeRequest->requested_by === (int) $user->getKey();
    }
}
