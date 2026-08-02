<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Employee;
use App\Models\LegalEntity;
use App\Models\User;
use App\Services\Organization\LegalEntityScope;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(LegalEntityScope $scope): View
    {
        $actor = request()->user();
        abort_unless($actor instanceof User, 403);
        $entityIds = $scope->idsFor($actor);

        return view('welcome', [
            'organizationMetrics' => [
                'legal_entities' => LegalEntity::query()->whereIn('id', $entityIds)->count(),
                'active_employees' => Employee::query()->whereIn('legal_entity_id', $entityIds)->where('status', 'active')->count(),
                'contracts_expiring' => Contract::query()
                    ->whereIn('legal_entity_id', $entityIds)
                    ->where('status', 'active')
                    ->whereBetween('end_date', [now()->toDateString(), now()->addDays(90)->toDateString()])
                    ->count(),
            ],
        ]);
    }
}
