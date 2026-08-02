<?php

namespace App\Policies;

use App\Models\EmployeeDocument;
use App\Models\User;
use App\Services\Organization\LegalEntityScope;

class EmployeeDocumentPolicy
{
    public function __construct(private readonly LegalEntityScope $scope) {}

    public function view(User $user, EmployeeDocument $document): bool
    {
        return $user->can('documents.view')
            && $this->scope->contains($user, (int) $document->legal_entity_id);
    }

    public function download(User $user, EmployeeDocument $document): bool
    {
        return $user->can('documents.download')
            && $user->can('employees.view-sensitive')
            && $this->scope->contains($user, (int) $document->legal_entity_id);
    }
}
