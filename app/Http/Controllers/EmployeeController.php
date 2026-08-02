<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Models\LegalEntity;
use App\Models\User;
use App\Services\Employee\EmployeeManager;
use App\Services\Organization\LegalEntityScope;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Employee::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $query = Employee::query()
            ->visibleTo($actor)
            ->search($request->string('search')->trim()->toString())
            ->with(['legalEntity', 'currentEmployment.branch', 'currentEmployment.department', 'currentEmployment.position']);

        if ($request->filled('legal_entity')) {
            $entity = LegalEntity::query()
                ->whereIn('id', app(LegalEntityScope::class)->idsFor($actor))
                ->where('public_id', $request->string('legal_entity')->toString())
                ->firstOrFail();
            $query->where('legal_entity_id', $entity->getKey());
        }

        return view('employees.index', [
            'employees' => $query->orderBy('full_name')->paginate(20)->withQueryString(),
            'legalEntities' => $this->scopedEntities($actor),
        ]);
    }

    public function create(Request $request): View
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        abort_unless($actor->can('employees.create'), 403);

        return view('employees.create', ['legalEntities' => $this->scopedEntities($actor, true)]);
    }

    public function store(StoreEmployeeRequest $request, EmployeeManager $manager): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $employee = $manager->create($actor, $request->scopedLegalEntity(), $request->validated());

        return redirect()->route('employees.show', $employee)->with('status', __('employee.status.created'));
    }

    public function show(Request $request, string $employee): View
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $scopeIds = app(LegalEntityScope::class)->idsFor($actor);
        $relations = [
            'legalEntity', 'currentEmployment.branch', 'currentEmployment.division', 'currentEmployment.department',
            'currentEmployment.position', 'currentEmployment.workLocation', 'currentEmployment.costCenter', 'currentEmployment.manager',
            'employmentHistories' => fn ($query) => $query->whereIn('legal_entity_id', $scopeIds),
            'employmentHistories.legalEntity', 'employmentHistories.branch',
            'employmentHistories.division', 'employmentHistories.department', 'employmentHistories.position',
            'employmentHistories.workLocation', 'employmentHistories.costCenter', 'employmentHistories.manager',
            'contracts' => fn ($query) => $query->whereIn('legal_entity_id', $scopeIds),
        ];

        if ($actor->can('employees.view-sensitive')) {
            $relations['contacts'] = fn ($query) => $query->whereIn('legal_entity_id', $scopeIds);
            $relations['emergencyContacts'] = fn ($query) => $query->whereIn('legal_entity_id', $scopeIds);
        }

        if ($actor->can('documents.view')) {
            $relations['documents'] = fn ($query) => $query->whereIn('legal_entity_id', $scopeIds);
        }

        if ($actor->can('employee-financial.view')) {
            $relations['bankAccounts'] = fn ($query) => $query->whereIn('legal_entity_id', $scopeIds);
            $relations['taxProfiles'] = fn ($query) => $query->whereIn('legal_entity_id', $scopeIds);
            $relations['bpjsProfiles'] = fn ($query) => $query->whereIn('legal_entity_id', $scopeIds);
        }

        $record = Employee::query()
            ->visibleTo($actor)
            ->where('public_id', $employee)
            ->with($relations)
            ->firstOrFail();
        $this->authorize('view', $record);
        $recordEntity = $record->legalEntity;
        abort_unless($recordEntity instanceof LegalEntity, 404);

        $canViewSensitive = $actor->can('viewSensitive', $record);
        $canViewFinancial = $actor->can('viewFinancial', $record);
        if ($canViewSensitive || $canViewFinancial) {
            activity('employee')
                ->causedBy($actor)
                ->performedOn($record)
                ->event('employee_sensitive_viewed')
                ->withProperties([
                    'legal_entity_public_id' => $recordEntity->public_id,
                    'sections' => array_values(array_filter([
                        $canViewSensitive ? 'identity_contacts_documents' : null,
                        $canViewFinancial ? 'financial_statutory_masked' : null,
                    ])),
                ])
                ->log('Authorized employee sensitive summary viewed.');
        }

        return view('employees.show', [
            'employee' => $record,
            'canViewSensitive' => $canViewSensitive,
            'canViewFinancial' => $canViewFinancial,
            'legalEntities' => $actor->can('update', $record) ? $this->scopedEntities($actor, true) : collect(),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, EmployeeManager $manager): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $employee = $manager->updateIdentity($actor, $request->scopedEmployee(), $request->validated());

        return redirect()->route('employees.show', $employee)->with('status', __('employee.status.updated'));
    }

    /** @return Collection<int, LegalEntity> */
    private function scopedEntities(User $actor, bool $withStructure = false): Collection
    {
        $query = LegalEntity::query()
            ->whereIn('id', app(LegalEntityScope::class)->idsFor($actor))
            ->where('status', 'active')
            ->orderBy('display_name');

        if ($withStructure) {
            $query->with([
                'branches' => fn ($query) => $query->where('status', 'active')->orderBy('name'),
                'divisions' => fn ($query) => $query->where('status', 'active')->orderBy('name'),
                'departments' => fn ($query) => $query->where('status', 'active')->orderBy('name'),
                'positions' => fn ($query) => $query->where('status', 'active')->orderBy('name'),
                'workLocations' => fn ($query) => $query->where('status', 'active')->orderBy('name'),
                'costCenters' => fn ($query) => $query->where('status', 'active')->orderBy('name'),
                'employees' => fn ($query) => $query->where('status', 'active')->orderBy('full_name'),
            ]);
        }

        return $query->get();
    }
}
