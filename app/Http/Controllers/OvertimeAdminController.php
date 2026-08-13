<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOvertimeDelegationRequest;
use App\Http\Requests\StoreOvertimeRuleRequest;
use App\Models\ApprovalDelegation;
use App\Models\LegalEntity;
use App\Models\OvertimeCalculation;
use App\Models\OvertimeRequest;
use App\Models\OvertimeRule;
use App\Models\User;
use App\Models\UserLegalEntityAccess;
use App\Services\Approval\ApprovalEngine;
use App\Services\Overtime\OvertimeManager;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OvertimeAdminController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('overtime.view') || $actor->can('overtime.manage') || $actor->can('overtime.report'), 403);
        $entityIds = $this->scopedEntityIds($actor);
        abort_if($entityIds === [], 403);

        return view('overtime.admin', [
            'entities' => LegalEntity::query()->whereIn('id', $entityIds)->orderBy('display_name')->get(),
            'rules' => OvertimeRule::query()->whereIn('legal_entity_id', $entityIds)->with('legalEntity')->latest('effective_from')->get(),
            'requests' => OvertimeRequest::query()->whereIn('legal_entity_id', $entityIds)
                ->with(['legalEntity', 'employee', 'rule', 'calculation'])->latest('submitted_at')->limit(100)->get(),
            'calculations' => OvertimeCalculation::query()->whereIn('legal_entity_id', $entityIds)
                ->with(['request.employee', 'rule'])->latest('calculated_at')->limit(100)->get(),
            'users' => User::query()->where('is_active', true)
                ->where(fn (Builder $query) => $query
                    ->whereHas('employee', fn (Builder $employee) => $employee->whereIn('legal_entity_id', $entityIds))
                    ->orWhereHas('legalEntityAccess', fn (Builder $access) => $access->whereIn('legal_entity_id', $entityIds)))
                ->orderBy('name')->get(),
            'delegations' => ApprovalDelegation::query()->whereIn('legal_entity_id', $entityIds)
                ->where('subject_type', 'overtime_request')->with(['legalEntity', 'delegate'])->latest()->limit(50)->get(),
            'canManage' => $actor->can('overtime.manage'), 'canExport' => $actor->can('reports.export'),
        ]);
    }

    public function storeRule(StoreOvertimeRuleRequest $request, OvertimeManager $manager): RedirectResponse
    {
        $actor = $this->actor($request);
        $entity = LegalEntity::query()->whereIn('id', $this->managedEntityIds($actor))
            ->where('public_id', $request->string('legal_entity_public_id'))->firstOrFail();
        $manager->createRule($actor, $entity, $request->validated());

        return redirect()->route('overtime.admin.index')->with('status', __('overtime.status.rule_created'));
    }

    public function storeDelegation(StoreOvertimeDelegationRequest $request, ApprovalEngine $approvals): RedirectResponse
    {
        $actor = $this->actor($request);
        $entity = LegalEntity::query()->whereIn('id', $this->managedEntityIds($actor))
            ->where('public_id', $request->string('legal_entity_public_id'))->firstOrFail();
        $delegator = $this->userInEntity($entity, (int) $request->integer('delegator_user_id'));
        $delegate = $this->userInEntity($entity, (int) $request->integer('delegate_user_id'));
        $approvals->createDelegation($actor, $entity, $delegator, $delegate, [
            'effective_from' => (string) $request->input('effective_from'),
            'effective_to' => (string) $request->input('effective_to'), 'reason' => (string) $request->input('reason'),
        ], 'overtime_request', 'overtime.approve-manager');

        return redirect()->route('overtime.admin.index')->with('status', __('overtime.status.delegation_created'));
    }

    public function revokeDelegation(Request $request, string $delegation, ApprovalEngine $approvals): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('overtime.manage'), 403);
        $record = ApprovalDelegation::query()->whereIn('legal_entity_id', $this->managedEntityIds($actor))
            ->where('subject_type', 'overtime_request')->where('public_id', $delegation)->firstOrFail();
        $approvals->revokeDelegation($actor, $record);

        return redirect()->route('overtime.admin.index')->with('status', __('overtime.status.delegation_revoked'));
    }

    public function export(Request $request): StreamedResponse
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('overtime.report') && $actor->can('reports.export'), 403);
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $entityIds = $this->scopedEntityIds($actor);
        $query = OvertimeRequest::query()->whereIn('legal_entity_id', $entityIds)
            ->with(['legalEntity', 'employee', 'rule', 'calculation']);
        if ($request->filled('from')) {
            $query->whereDate('work_date', '>=', $request->date('from')?->toDateString());
        }
        if ($request->filled('to')) {
            $query->whereDate('work_date', '<=', $request->date('to')?->toDateString());
        }
        activity('overtime')->causedBy($actor)->event('overtime_report_exported')
            ->withProperties(['legal_entity_count' => count($entityIds), 'format' => 'csv'])
            ->log('Authorized overtime report exported.');

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                return;
            }
            fputcsv($output, ['legal_entity', 'employee_number', 'employee', 'work_date', 'day_type', 'request_type', 'planned_minutes', 'approved_minutes', 'actual_minutes', 'payable_minutes', 'weighted_minutes_hundredths', 'meal_allowance_idr', 'transport_allowance_idr', 'payroll_period', 'status']);
            $query->orderBy('work_date')->chunkById(200, function ($records) use ($output): void {
                foreach ($records as $record) {
                    fputcsv($output, [
                        $record->legalEntity?->code, $record->employee?->employee_number, $record->employee?->full_name,
                        (string) $record->getRawOriginal('work_date'), $record->dayType()->value,
                        $record->requestType()->value, $record->planned_minutes, $record->approved_minutes,
                        $record->actual_minutes, $record->payable_minutes, $record->weighted_minutes_hundredths,
                        $record->meal_allowance_idr, $record->transport_allowance_idr,
                        $record->payroll_period_key, $record->requestStatus()->value,
                    ]);
                }
            });
            fclose($output);
        }, 'overtime-report-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
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
        return array_values(UserLegalEntityAccess::query()->where('user_id', $actor->getKey())->where('access_level', 'manage')
            ->effectiveOn(now()->toDateString())->pluck('legal_entity_id')
            ->map(static fn (mixed $id): int => (int) $id)->values()->all());
    }

    /** @return list<int> */
    private function scopedEntityIds(User $actor): array
    {
        return array_values(UserLegalEntityAccess::query()->where('user_id', $actor->getKey())->effectiveOn(now()->toDateString())
            ->pluck('legal_entity_id')->map(static fn (mixed $id): int => (int) $id)->values()->all());
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
