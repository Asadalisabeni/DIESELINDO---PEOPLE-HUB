<?php

namespace App\Policies;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\LegalEntity;
use App\Models\User;
use App\Services\Organization\LegalEntityScope;

class EmployeePolicy
{
    public function __construct(private readonly LegalEntityScope $scope) {}

    public function viewAny(User $user): bool
    {
        return $user->can('employees.view') && $this->scope->idsFor($user) !== [];
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->can('employees.view') && $this->scope->contains($user, (int) $employee->legal_entity_id);
    }

    public function viewSensitive(User $user, Employee $employee): bool
    {
        return $user->can('employees.view-sensitive') && $this->scope->contains($user, (int) $employee->legal_entity_id);
    }

    public function viewFinancial(User $user, Employee $employee): bool
    {
        return $user->can('employee-financial.view') && $this->scope->contains($user, (int) $employee->legal_entity_id);
    }

    public function create(User $user, LegalEntity $entity): bool
    {
        return $user->can('employees.create') && $this->scope->manages($user, (int) $entity->getKey());
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->can('employees.update') && $this->scope->manages($user, (int) $employee->legal_entity_id);
    }

    public function manageContract(User $user, Employee $employee): bool
    {
        return $user->can('contracts.manage') && $this->scope->manages($user, (int) $employee->legal_entity_id);
    }

    public function uploadDocument(User $user, Employee $employee): bool
    {
        return $user->can('documents.upload') && $this->scope->manages($user, (int) $employee->legal_entity_id);
    }

    public function viewSelfService(User $user, Employee $employee): bool
    {
        return $user->can('ess.access')
            && (int) $user->employee_id === (int) $employee->getKey()
            && EmployeeStatus::tryFrom((string) $employee->getRawOriginal('status')) === EmployeeStatus::Active;
    }

    public function updateSelfService(User $user, Employee $employee): bool
    {
        return $user->can('ess.profile.update') && $this->viewSelfService($user, $employee);
    }

    public function requestProfileChange(User $user, Employee $employee): bool
    {
        return $user->can('ess.profile-change.request') && $this->viewSelfService($user, $employee);
    }
}
