<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLegalEntityRequest;
use App\Http\Requests\StoreOrganizationUnitRequest;
use App\Http\Requests\UpdateLegalEntityRequest;
use App\Http\Requests\UpdateOrganizationUnitRequest;
use App\Models\LegalEntity;
use App\Models\User;
use App\Services\Organization\LegalEntityScope;
use App\Services\Organization\OrganizationStructureManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class OrganizationController extends Controller
{
    public function index(LegalEntityScope $scope): View
    {
        $this->authorize('viewAny', LegalEntity::class);
        $actor = request()->user();
        abort_unless($actor instanceof User, 403);

        $entities = LegalEntity::query()
            ->whereIn('id', $scope->idsFor($actor))
            ->with([
                'branches' => fn ($query) => $query->orderBy('name'),
                'divisions' => fn ($query) => $query->orderBy('name'),
                'departments.branch', 'departments.division',
                'positions.department',
                'workLocations' => fn ($query) => $query->orderBy('name'),
                'costCenters' => fn ($query) => $query->orderBy('name'),
            ])
            ->withCount(['employees', 'branches', 'departments', 'positions'])
            ->orderBy('display_name')
            ->get();

        return view('organization.index', compact('entities'));
    }

    public function store(StoreLegalEntityRequest $request, OrganizationStructureManager $manager): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $manager->createLegalEntity($actor, $request->validated());

        return back()->with('status', __('organization.status.entity_created'));
    }

    public function update(UpdateLegalEntityRequest $request, OrganizationStructureManager $manager): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $manager->updateLegalEntity($actor, $request->scopedLegalEntity(), $request->validated());

        return back()->with('status', __('organization.status.entity_updated'));
    }

    public function storeUnit(
        StoreOrganizationUnitRequest $request,
        string $legalEntity,
        string $unitType,
        OrganizationStructureManager $manager,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $manager->createUnit($actor, $request->scopedLegalEntity(), $unitType, $request->validated());

        return back()->with('status', __('organization.status.unit_created'));
    }

    public function updateUnit(
        UpdateOrganizationUnitRequest $request,
        string $legalEntity,
        string $unitType,
        string $unit,
        OrganizationStructureManager $manager,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $manager->updateUnit(
            $actor,
            $request->scopedLegalEntity(),
            $unitType,
            $request->scopedUnit(),
            $request->validated(),
        );

        return back()->with('status', __('organization.status.unit_updated'));
    }
}
