<?php

namespace App\Http\Controllers;

use App\Models\AuditActivity;
use App\Models\AuthenticationEvent;
use App\Models\LegalEntity;
use App\Models\User;
use App\Services\Organization\LegalEntityScope;
use Illuminate\Contracts\View\View;

class AuditLogController extends Controller
{
    public function __invoke(LegalEntityScope $scope): View
    {
        $actor = request()->user();
        abort_unless($actor instanceof User && $actor->can('audit.view'), 403);
        $entityPublicIds = LegalEntity::query()
            ->whereIn('id', $scope->idsFor($actor))
            ->pluck('public_id')
            ->all();

        $activities = AuditActivity::query()
            ->where(function ($query) use ($entityPublicIds): void {
                $query->whereNotIn('log_name', ['employee', 'organization']);

                if ($entityPublicIds !== []) {
                    $query->orWhereIn('properties->legal_entity_public_id', $entityPublicIds);
                }
            })
            ->with(['causer', 'subject'])
            ->latest()
            ->paginate(30, ['*'], 'activity_page');
        $authenticationEvents = AuthenticationEvent::query()
            ->with('user')
            ->latest('occurred_at')
            ->paginate(30, ['*'], 'auth_page');

        return view('audit.index', compact('activities', 'authenticationEvents'));
    }
}
