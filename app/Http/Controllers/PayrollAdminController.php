<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePayrollGroupRequest;
use App\Http\Requests\StorePayrollMembershipRequest;
use App\Http\Requests\StorePayrollPeriodRequest;
use App\Http\Requests\StorePayrollRunRequest;
use App\Http\Requests\StoreSalaryComponentRequest;
use App\Http\Requests\StoreSalaryHistoryRequest;
use App\Models\Employee;
use App\Models\LegalEntity;
use App\Models\PayrollGroup;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\SalaryComponent;
use App\Models\SalaryHistory;
use App\Models\User;
use App\Models\UserLegalEntityAccess;
use App\Services\Payroll\PayrollManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollAdminController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('payroll.view') || $actor->can('salaries.view'), 403);
        $entityIds = $this->scopedEntityIds($actor);
        abort_if($entityIds === [], 403);
        $canViewPayroll = $actor->can('payroll.view');
        $selectedRun = null;
        if ($request->filled('run')) {
            abort_unless($canViewPayroll, 403);
            $selectedRun = PayrollRun::query()->whereIn('legal_entity_id', $entityIds)
                ->where('public_id', $request->string('run'))->with(['period.group', 'employees.items', 'findings.runEmployee'])->firstOrFail();
        }

        $periods = $canViewPayroll
            ? PayrollPeriod::query()->whereIn('legal_entity_id', $entityIds)->with(['group', 'runs'])->latest('payroll_end')->limit(50)->get()
            : collect();
        $runs = $canViewPayroll
            ? PayrollRun::query()->whereIn('legal_entity_id', $entityIds)->with('period.group')->withCount(['employees', 'findings'])->latest()->limit(50)->get()
            : collect();

        return view('payroll.admin', [
            'entities' => LegalEntity::query()->whereIn('id', $entityIds)->orderBy('display_name')->get(),
            'components' => SalaryComponent::query()->whereIn('legal_entity_id', $entityIds)->with('legalEntity')->latest('effective_from')->get(),
            'groups' => PayrollGroup::query()->whereIn('legal_entity_id', $entityIds)->with(['legalEntity', 'memberships.employee'])->orderBy('name')->get(),
            'employees' => Employee::query()->whereIn('legal_entity_id', $entityIds)->orderBy('full_name')->get(),
            'salaries' => SalaryHistory::query()->whereIn('legal_entity_id', $entityIds)->with(['employee', 'creator', 'approver', 'components.component'])->latest('effective_from')->limit(100)->get(),
            'periods' => $periods,
            'runs' => $runs,
            'selectedRun' => $selectedRun, 'canConfigureSalary' => $actor->can('salaries.update'),
            'canApproveSalary' => $actor->can('salaries.approve'), 'canPrepare' => $actor->can('payroll.prepare'),
            'canValidate' => $actor->can('payroll.validate'), 'canExport' => $actor->can('reports.export'),
            'canViewPayroll' => $canViewPayroll,
        ]);
    }

    public function storeComponent(StoreSalaryComponentRequest $request, PayrollManager $manager): RedirectResponse
    {
        $actor = $this->actor($request);
        $entity = $this->managedEntity($actor, (string) $request->input('legal_entity_public_id'));
        $manager->createComponent($actor, $entity, $request->validated());

        return $this->back(__('payroll.status.component_created'));
    }

    public function storeGroup(StorePayrollGroupRequest $request, PayrollManager $manager): RedirectResponse
    {
        $actor = $this->actor($request);
        $entity = $this->managedEntity($actor, (string) $request->input('legal_entity_public_id'));
        $manager->createGroup($actor, $entity, $request->validated());

        return $this->back(__('payroll.status.group_created'));
    }

    public function storeMembership(StorePayrollMembershipRequest $request, PayrollManager $manager): RedirectResponse
    {
        $actor = $this->actor($request);
        $managed = $this->managedEntityIds($actor);
        $group = PayrollGroup::query()->whereIn('legal_entity_id', $managed)->where('public_id', $request->string('payroll_group_public_id'))->firstOrFail();
        $employee = Employee::query()->where('legal_entity_id', $group->legal_entity_id)->where('public_id', $request->string('employee_public_id'))->firstOrFail();
        $manager->assignMembership($actor, $group, $employee, $request->validated());

        return $this->back(__('payroll.status.membership_created'));
    }

    public function storeSalary(StoreSalaryHistoryRequest $request, PayrollManager $manager): RedirectResponse
    {
        $actor = $this->actor($request);
        $employee = Employee::query()->whereIn('legal_entity_id', $this->managedEntityIds($actor))
            ->where('public_id', $request->string('employee_public_id'))->firstOrFail();
        $manager->createSalary($actor, $employee, $request->validated());

        return $this->back(__('payroll.status.salary_drafted'));
    }

    public function approveSalary(Request $request, string $salaryHistory, PayrollManager $manager): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('salaries.approve'), 403);
        $history = SalaryHistory::query()->whereIn('legal_entity_id', $this->managedEntityIds($actor))->where('public_id', $salaryHistory)->firstOrFail();
        $manager->approveSalary($actor, $history);

        return $this->back(__('payroll.status.salary_approved'));
    }

    public function storePeriod(StorePayrollPeriodRequest $request, PayrollManager $manager): RedirectResponse
    {
        $actor = $this->actor($request);
        $group = PayrollGroup::query()->whereIn('legal_entity_id', $this->managedEntityIds($actor))
            ->where('public_id', $request->string('payroll_group_public_id'))->firstOrFail();
        $manager->createPeriod($actor, $group, $request->validated());

        return $this->back(__('payroll.status.period_created'));
    }

    public function storeRun(StorePayrollRunRequest $request, string $payrollPeriod, PayrollManager $manager): RedirectResponse
    {
        $actor = $this->actor($request);
        $period = PayrollPeriod::query()->whereIn('legal_entity_id', $this->managedEntityIds($actor))
            ->where('public_id', $payrollPeriod)->firstOrFail();
        $manager->createRun($actor, $period, $request->validated());

        return $this->back(__('payroll.status.run_created'));
    }

    public function calculate(Request $request, string $payrollRun, PayrollManager $manager): RedirectResponse
    {
        $actor = $this->actor($request);
        $run = $this->managedRun($actor, $payrollRun);
        $calculated = $manager->calculate($actor, $run);

        return redirect()->route('payroll.admin.index', ['run' => $calculated->public_id])->with('status', __('payroll.status.run_calculated'));
    }

    public function validateRun(Request $request, string $payrollRun, PayrollManager $manager): RedirectResponse
    {
        $actor = $this->actor($request);
        $run = $this->managedRun($actor, $payrollRun);
        $validated = $manager->validateRun($actor, $run);

        return redirect()->route('payroll.admin.index', ['run' => $validated->public_id])->with('status', __('payroll.status.run_validated'));
    }

    public function export(Request $request, string $payrollRun): StreamedResponse
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('reports.export') && $actor->can('payroll.view'), 403);
        $run = PayrollRun::query()->whereIn('legal_entity_id', $this->scopedEntityIds($actor))->where('public_id', $payrollRun)
            ->with(['period.group', 'employees.items'])->firstOrFail();
        activity('payroll')->causedBy($actor)->performedOn($run)->event('payroll_register_exported')
            ->withProperties(['run_public_id' => $run->public_id, 'period_key' => $run->period?->period_key, 'format' => 'csv'])
            ->log('Authorized payroll register exported.');

        return response()->streamDownload(function () use ($run): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['employee_number', 'employee', 'service_from', 'service_to', 'payable_days', 'period_days', 'gross', 'deductions', 'employer', 'tax', 'bpjs', 'net', 'validation_status', 'snapshot_checksum']);
            foreach ($run->employees as $employee) {
                $snapshot = $employee->employeeSnapshot();
                fputcsv($out, [$snapshot['employee_number'] ?? '', $snapshot['full_name'] ?? '',
                    $employee->serviceFrom()->toDateString(), $employee->serviceTo()->toDateString(), $employee->payable_days,
                    $employee->period_days, $employee->gross_total, $employee->deduction_total, $employee->employer_total,
                    $employee->tax_total, $employee->bpjs_total, $employee->net_total, $employee->validation_status, $employee->snapshot_checksum]);
            }
            fclose($out);
        }, 'payroll-register-'.$run->period?->period_key.'-v'.$run->version.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function managedRun(User $actor, string $publicId): PayrollRun
    {
        return PayrollRun::query()->whereIn('legal_entity_id', $this->managedEntityIds($actor))->where('public_id', $publicId)->firstOrFail();
    }

    private function managedEntity(User $actor, string $publicId): LegalEntity
    {
        return LegalEntity::query()->whereIn('id', $this->managedEntityIds($actor))->where('public_id', $publicId)->firstOrFail();
    }

    /** @return list<int> */
    private function managedEntityIds(User $actor): array
    {
        return array_values(UserLegalEntityAccess::query()->where('user_id', $actor->getKey())->where('access_level', 'manage')
            ->effectiveOn(now()->toDateString())->pluck('legal_entity_id')->map(static fn (mixed $id): int => (int) $id)->all());
    }

    /** @return list<int> */
    private function scopedEntityIds(User $actor): array
    {
        return array_values(UserLegalEntityAccess::query()->where('user_id', $actor->getKey())->effectiveOn(now()->toDateString())
            ->pluck('legal_entity_id')->map(static fn (mixed $id): int => (int) $id)->all());
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }

    private function back(string $message): RedirectResponse
    {
        return redirect()->route('payroll.admin.index')->with('status', $message);
    }
}
