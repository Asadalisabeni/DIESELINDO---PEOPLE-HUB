<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdjustLeaveBalanceRequest;
use App\Http\Requests\GrantLeaveEntitlementRequest;
use App\Http\Requests\StoreApprovalDelegationRequest;
use App\Http\Requests\StoreLeavePolicyRequest;
use App\Http\Requests\StoreLeaveTypeRequest;
use App\Models\ApprovalDelegation;
use App\Models\Employee;
use App\Models\LeaveEntitlement;
use App\Models\LeaveLedgerEntry;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\LegalEntity;
use App\Models\User;
use App\Models\UserLegalEntityAccess;
use App\Services\Approval\ApprovalEngine;
use App\Services\Leave\LeaveManager;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveAdminController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('leave.view') || $actor->can('leave.manage') || $actor->can('leave.report'), 403);
        $entityIds = $this->scopedEntityIds($actor);
        abort_if($entityIds === [], 403);

        return view('leave.admin', [
            'entities' => LegalEntity::query()->whereIn('id', $entityIds)->orderBy('display_name')->get(),
            'types' => LeaveType::query()->whereIn('legal_entity_id', $entityIds)->with(['legalEntity', 'policies'])->orderBy('name')->get(),
            'employees' => Employee::query()->whereIn('legal_entity_id', $entityIds)->orderBy('full_name')->get(),
            'entitlements' => LeaveEntitlement::query()->whereIn('legal_entity_id', $entityIds)
                ->with(['employee', 'type'])->withSum('ledgerEntries as balance', 'quantity')->latest()->limit(100)->get(),
            'ledger' => LeaveLedgerEntry::query()->whereIn('legal_entity_id', $entityIds)
                ->with(['entitlement.type', 'entitlement.employee'])->latest('effective_date')->limit(100)->get(),
            'requests' => LeaveRequest::query()->whereIn('legal_entity_id', $entityIds)
                ->with(['employee', 'type'])->latest('submitted_at')->limit(100)->get(),
            'users' => User::query()->where('is_active', true)
                ->where(fn (Builder $query) => $query
                    ->whereHas('employee', fn (Builder $employee) => $employee->whereIn('legal_entity_id', $entityIds))
                    ->orWhereHas('legalEntityAccess', fn (Builder $access) => $access->whereIn('legal_entity_id', $entityIds)))
                ->orderBy('name')->get(),
            'delegations' => ApprovalDelegation::query()->whereIn('legal_entity_id', $entityIds)
                ->with(['legalEntity', 'delegate'])->latest()->limit(50)->get(),
            'canManage' => $actor->can('leave.manage'),
            'canAdjust' => $actor->can('leave.adjust'),
            'canExport' => $actor->can('reports.export'),
        ]);
    }

    public function storeType(StoreLeaveTypeRequest $request, LeaveManager $manager): RedirectResponse
    {
        $actor = $this->actor($request);
        $entity = LegalEntity::query()->whereIn('id', $this->managedEntityIds($actor))
            ->where('public_id', $request->string('legal_entity_public_id'))->firstOrFail();
        $manager->createType($actor, $entity, $request->validated());

        return redirect()->route('leave.admin.index')->with('status', __('leave.status.type_created'));
    }

    public function storePolicy(StoreLeavePolicyRequest $request, LeaveManager $manager): RedirectResponse
    {
        $actor = $this->actor($request);
        $type = LeaveType::query()->whereIn('legal_entity_id', $this->managedEntityIds($actor))
            ->where('public_id', $request->string('leave_type_public_id'))->firstOrFail();
        $manager->createPolicy($actor, $type, $request->validated());

        return redirect()->route('leave.admin.index')->with('status', __('leave.status.policy_created'));
    }

    public function grant(GrantLeaveEntitlementRequest $request, LeaveManager $manager): RedirectResponse
    {
        $actor = $this->actor($request);
        $entityIds = $this->managedEntityIds($actor);
        $employee = Employee::query()->whereIn('legal_entity_id', $entityIds)
            ->where('public_id', $request->string('employee_public_id'))->firstOrFail();
        $type = LeaveType::query()->where('legal_entity_id', $employee->legal_entity_id)
            ->where('public_id', $request->string('leave_type_public_id'))->firstOrFail();
        $manager->grantEntitlement($actor, $employee, $type, $request->validated());

        return redirect()->route('leave.admin.index')->with('status', __('leave.status.entitlement_granted'));
    }

    public function adjust(
        AdjustLeaveBalanceRequest $request,
        string $entitlement,
        LeaveManager $manager,
    ): RedirectResponse {
        $actor = $this->actor($request);
        $record = LeaveEntitlement::query()->whereIn('legal_entity_id', $this->managedEntityIds($actor))
            ->where('public_id', $entitlement)->firstOrFail();
        $manager->adjust($actor, $record, $request->validated());

        return redirect()->route('leave.admin.index')->with('status', __('leave.status.balance_adjusted'));
    }

    public function storeDelegation(
        StoreApprovalDelegationRequest $request,
        ApprovalEngine $approvals,
    ): RedirectResponse {
        $actor = $this->actor($request);
        $entity = LegalEntity::query()->whereIn('id', $this->managedEntityIds($actor))
            ->where('public_id', $request->string('legal_entity_public_id'))->firstOrFail();
        $delegator = $this->userInEntity($entity, (int) $request->integer('delegator_user_id'));
        $delegate = $this->userInEntity($entity, (int) $request->integer('delegate_user_id'));
        $approvals->createDelegation($actor, $entity, $delegator, $delegate, [
            'effective_from' => (string) $request->input('effective_from'),
            'effective_to' => (string) $request->input('effective_to'),
            'reason' => (string) $request->input('reason'),
        ]);

        return redirect()->route('leave.admin.index')->with('status', __('leave.status.delegation_created'));
    }

    public function revokeDelegation(Request $request, string $delegation, ApprovalEngine $approvals): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('leave.manage'), 403);
        $record = ApprovalDelegation::query()->whereIn('legal_entity_id', $this->managedEntityIds($actor))
            ->where('public_id', $delegation)->firstOrFail();
        $approvals->revokeDelegation($actor, $record);

        return redirect()->route('leave.admin.index')->with('status', __('leave.status.delegation_revoked'));
    }

    public function export(Request $request): StreamedResponse
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('leave.report') && $actor->can('reports.export'), 403);
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $entityIds = $this->scopedEntityIds($actor);
        $query = LeaveRequest::query()->whereIn('legal_entity_id', $entityIds)->with(['legalEntity', 'employee', 'type']);
        if ($request->filled('from')) {
            $query->whereDate('start_date', '>=', $request->date('from')?->toDateString());
        }
        if ($request->filled('to')) {
            $query->whereDate('end_date', '<=', $request->date('to')?->toDateString());
        }

        activity('leave')->causedBy($actor)->event('leave_report_exported')
            ->withProperties(['legal_entity_count' => count($entityIds), 'format' => 'csv'])
            ->log('Authorized leave report exported.');

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                return;
            }
            fputcsv($output, ['legal_entity', 'employee_number', 'employee', 'leave_type', 'start_date', 'end_date', 'days', 'status']);
            $query->orderBy('start_date')->chunkById(200, function ($requests) use ($output): void {
                foreach ($requests as $leaveRequest) {
                    fputcsv($output, [
                        $leaveRequest->legalEntity?->code,
                        $leaveRequest->employee?->employee_number,
                        $leaveRequest->employee?->full_name,
                        $leaveRequest->type?->name,
                        (string) $leaveRequest->getRawOriginal('start_date'),
                        (string) $leaveRequest->getRawOriginal('end_date'),
                        $leaveRequest->total_days,
                        $leaveRequest->requestStatus()->value,
                    ]);
                }
            });
            fclose($output);
        }, 'leave-report-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function userInEntity(LegalEntity $entity, int $userId): User
    {
        return User::query()->whereKey($userId)->where('is_active', true)
            ->where(fn (Builder $query) => $query
                ->whereHas('employee', fn (Builder $employee) => $employee->where('legal_entity_id', $entity->getKey()))
                ->orWhereHas('legalEntityAccess', fn (Builder $access) => $access->where('legal_entity_id', $entity->getKey())))
            ->firstOrFail();
    }

    /** @return list<int> */
    private function managedEntityIds(User $actor): array
    {
        $ids = UserLegalEntityAccess::query()->where('user_id', $actor->getKey())
            ->where('access_level', 'manage')->effectiveOn(now()->toDateString())
            ->pluck('legal_entity_id')->map(static fn (mixed $id): int => (int) $id)->values()->all();

        return array_values($ids);
    }

    /** @return list<int> */
    private function scopedEntityIds(User $actor): array
    {
        $ids = UserLegalEntityAccess::query()->where('user_id', $actor->getKey())
            ->effectiveOn(now()->toDateString())->pluck('legal_entity_id')
            ->map(static fn (mixed $id): int => (int) $id)->values()->all();

        return array_values($ids);
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
