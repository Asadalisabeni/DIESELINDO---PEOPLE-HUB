<?php

namespace App\Services\Payroll;

use App\Enums\PayrollPeriodStatus;
use App\Enums\PayrollRunStatus;
use App\Enums\SalaryComponentType;
use App\Enums\SalaryHistoryStatus;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use App\Models\EmployeeScheduleAssignment;
use App\Models\EmploymentHistory;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LegalEntity;
use App\Models\OvertimeCalculation;
use App\Models\PayrollGroup;
use App\Models\PayrollGroupMembership;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunEmployee;
use App\Models\PayrollValidationFinding;
use App\Models\SalaryComponent;
use App\Models\SalaryHistory;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\Organization\LegalEntityScope;
use App\Support\Payroll\DecimalMath;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollManager
{
    public function __construct(private readonly LegalEntityScope $scope) {}

    /** @param array<string, mixed> $data */
    public function createComponent(User $actor, LegalEntity $entity, array $data): SalaryComponent
    {
        $this->assertManage($actor, $entity, 'salaries.update');

        return DB::transaction(function () use ($actor, $entity, $data): SalaryComponent {
            $overlap = SalaryComponent::query()->where('legal_entity_id', $entity->getKey())
                ->where('code', strtoupper((string) $data['code']))->where('status', 'active')
                ->whereDate('effective_from', '<=', $data['effective_to'] ?? '9999-12-31')
                ->where(fn (Builder $period) => $period->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $data['effective_from']))
                ->lockForUpdate()->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['effective_from' => __('payroll.validation.component_overlap')]);
            }
            $component = SalaryComponent::query()->create([
                'legal_entity_id' => $entity->getKey(), 'code' => strtoupper(trim((string) $data['code'])),
                'name' => trim((string) $data['name']), 'type' => $data['type'],
                'calculation_type' => $data['calculation_type'], 'taxable' => (bool) ($data['taxable'] ?? false),
                'bpjs_eligible' => (bool) ($data['bpjs_eligible'] ?? false), 'currency' => $entity->currency,
                'rounding_scale' => (int) $data['rounding_scale'], 'rounding_mode' => $data['rounding_mode'],
                'effective_from' => $data['effective_from'], 'effective_to' => $data['effective_to'] ?? null,
                'status' => 'active', 'approved_by' => $actor->getKey(), 'approved_at' => now(),
                'created_by' => $actor->getKey(),
            ]);
            activity('payroll')->causedBy($actor)->performedOn($component)->event('salary_component_created')
                ->withProperties(['legal_entity_public_id' => $entity->public_id, 'component_public_id' => $component->public_id,
                    'code' => $component->code, 'type' => $component->componentType()->value,
                    'calculation_type' => $component->calculation_type, 'effective_from' => $data['effective_from']])
                ->log('Effective-dated salary component created.');

            return $component;
        });
    }

    /** @param array<string, mixed> $data */
    public function createGroup(User $actor, LegalEntity $entity, array $data): PayrollGroup
    {
        $this->assertManage($actor, $entity, 'payroll.prepare');

        return DB::transaction(function () use ($actor, $entity, $data): PayrollGroup {
            $group = PayrollGroup::query()->create([
                'legal_entity_id' => $entity->getKey(), 'code' => strtoupper(trim((string) $data['code'])),
                'name' => trim((string) $data['name']), 'frequency' => 'monthly',
                'timezone' => $entity->timezone, 'currency' => $entity->currency,
                'proration_basis' => $data['proration_basis'], 'cutoff_start_day' => (int) $data['cutoff_start_day'],
                'cutoff_end_day' => (int) $data['cutoff_end_day'], 'payment_day' => (int) $data['payment_day'],
                'payment_date_adjustment' => $data['payment_date_adjustment'], 'status' => 'active',
                'created_by' => $actor->getKey(),
            ]);
            activity('payroll')->causedBy($actor)->performedOn($group)->event('payroll_group_created')
                ->withProperties(['legal_entity_public_id' => $entity->public_id, 'group_public_id' => $group->public_id,
                    'code' => $group->code, 'proration_basis' => $group->proration_basis])
                ->log('Configurable payroll group created.');

            return $group;
        });
    }

    /** @param array<string, mixed> $data */
    public function assignMembership(User $actor, PayrollGroup $group, Employee $employee, array $data): PayrollGroupMembership
    {
        $entity = $group->legalEntity()->firstOrFail();
        $this->assertManage($actor, $entity, 'payroll.prepare');
        abort_unless((int) $employee->legal_entity_id === (int) $group->legal_entity_id, 404);

        return DB::transaction(function () use ($actor, $group, $employee, $data): PayrollGroupMembership {
            $overlap = PayrollGroupMembership::query()->where('employee_id', $employee->getKey())
                ->overlapping((string) $data['effective_from'], (string) ($data['effective_to'] ?? '9999-12-31'))
                ->lockForUpdate()->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['effective_from' => __('payroll.validation.membership_overlap')]);
            }
            $membership = PayrollGroupMembership::query()->create([
                'legal_entity_id' => $group->legal_entity_id, 'payroll_group_id' => $group->getKey(),
                'employee_id' => $employee->getKey(), 'effective_from' => $data['effective_from'],
                'effective_to' => $data['effective_to'] ?? null, 'reason' => trim((string) $data['reason']),
                'source' => 'manual', 'created_by' => $actor->getKey(),
            ]);
            activity('payroll')->causedBy($actor)->performedOn($membership)->event('payroll_membership_created')
                ->withProperties(['group_public_id' => $group->public_id, 'employee_public_id' => $employee->public_id,
                    'effective_from' => $data['effective_from'], 'effective_to' => $data['effective_to'] ?? null])
                ->log('Effective payroll group membership created.');

            return $membership;
        });
    }

    /** @param array<string, mixed> $data */
    public function createSalary(User $actor, Employee $employee, array $data): SalaryHistory
    {
        $entity = $employee->legalEntity()->firstOrFail();
        $this->assertManage($actor, $entity, 'salaries.update');
        /** @var list<array{component_public_id: string, amount: string, quantity?: string}> $lines */
        $lines = $data['components'];

        return DB::transaction(function () use ($actor, $employee, $entity, $data, $lines): SalaryHistory {
            if (SalaryHistory::query()->where('employee_id', $employee->getKey())
                ->whereDate('effective_from', $data['effective_from'])->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['effective_from' => __('payroll.validation.salary_start_exists')]);
            }
            $resolved = [];
            $income = '0.0000';
            $deduction = '0.0000';
            foreach ($lines as $index => $line) {
                $component = SalaryComponent::query()->where('legal_entity_id', $entity->getKey())
                    ->where('public_id', $line['component_public_id'])->effectiveOn((string) $data['effective_from'])->firstOrFail();
                if (! in_array($component->calculation_type, ['fixed_monthly', 'daily_rate', 'unpaid_leave_daily'], true)) {
                    throw ValidationException::withMessages(['components.'.$index => __('payroll.validation.invalid_salary_component')]);
                }
                $amount = DecimalMath::normalize($line['amount']);
                $quantity = DecimalMath::normalize($line['quantity'] ?? '1');
                $resolved[] = ['component' => $component, 'amount' => $amount, 'quantity' => $quantity, 'sequence' => $index + 1];
                $lineTotal = DecimalMath::multiply($amount, $quantity);
                if ($component->componentType() === SalaryComponentType::Income) {
                    $income = DecimalMath::add($income, $lineTotal);
                } elseif ($component->componentType() === SalaryComponentType::Deduction) {
                    $deduction = DecimalMath::add($deduction, $lineTotal);
                }
            }
            $snapshot = array_map(static fn (array $line): array => [
                'component_public_id' => $line['component']->public_id, 'code' => $line['component']->code,
                'amount' => $line['amount'], 'quantity' => $line['quantity'], 'sequence' => $line['sequence'],
            ], $resolved);
            $checksum = hash('sha256', json_encode([
                'employee_public_id' => $employee->public_id, 'effective_from' => $data['effective_from'],
                'effective_to' => $data['effective_to'] ?? null, 'currency' => $entity->currency, 'lines' => $snapshot,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $history = SalaryHistory::query()->create([
                'legal_entity_id' => $entity->getKey(), 'employee_id' => $employee->getKey(),
                'currency' => $entity->currency, 'effective_from' => $data['effective_from'],
                'effective_to' => $data['effective_to'] ?? null, 'status' => SalaryHistoryStatus::Draft->value,
                'reason' => trim((string) $data['reason']), 'monthly_income_total' => $income,
                'monthly_deduction_total' => $deduction, 'version_checksum' => $checksum,
                'created_by' => $actor->getKey(),
            ]);
            foreach ($resolved as $line) {
                $history->components()->create([
                    'legal_entity_id' => $entity->getKey(), 'salary_component_id' => $line['component']->getKey(),
                    'sequence' => $line['sequence'], 'amount' => $line['amount'], 'quantity' => $line['quantity'],
                ]);
            }
            activity('payroll')->causedBy($actor)->performedOn($history)->event('salary_history_drafted')
                ->withProperties(['legal_entity_public_id' => $entity->public_id, 'employee_public_id' => $employee->public_id,
                    'salary_history_public_id' => $history->public_id, 'effective_from' => $data['effective_from'],
                    'component_count' => count($resolved), 'version_checksum' => $checksum])
                ->log('Salary version drafted for independent approval.');

            return $history->load('components.component');
        });
    }

    public function approveSalary(User $actor, SalaryHistory $history): SalaryHistory
    {
        $entity = $history->legalEntity()->firstOrFail();
        $this->assertManage($actor, $entity, 'salaries.approve');

        return DB::transaction(function () use ($actor, $history): SalaryHistory {
            $locked = SalaryHistory::query()->whereKey($history->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->historyStatus() !== SalaryHistoryStatus::Draft || (int) $locked->created_by === (int) $actor->getKey()) {
                throw ValidationException::withMessages(['salary' => __('payroll.validation.invalid_salary_approval')]);
            }
            $later = SalaryHistory::query()->where('employee_id', $locked->employee_id)
                ->where('status', SalaryHistoryStatus::Approved->value)
                ->whereDate('effective_from', '>=', $locked->effectiveFrom()->toDateString())
                ->lockForUpdate()->exists();
            if ($later) {
                throw ValidationException::withMessages(['salary' => __('payroll.validation.salary_sequence_conflict')]);
            }
            $locked->update(['status' => SalaryHistoryStatus::Approved->value, 'approved_by' => $actor->getKey(), 'approved_at' => now()]);
            activity('payroll')->causedBy($actor)->performedOn($locked)->event('salary_history_approved')
                ->withProperties(['salary_history_public_id' => $locked->public_id,
                    'employee_public_id' => $locked->employee()->value('public_id'), 'version_checksum' => $locked->version_checksum])
                ->log('Salary version independently approved.');

            return $locked->refresh()->load('components.component');
        });
    }

    /** @param array<string, mixed> $data */
    public function createPeriod(User $actor, PayrollGroup $group, array $data): PayrollPeriod
    {
        $entity = $group->legalEntity()->firstOrFail();
        $this->assertManage($actor, $entity, 'payroll.prepare');
        $calendar = [
            'group_public_id' => $group->public_id, 'frequency' => $group->frequency, 'timezone' => $group->timezone,
            'currency' => $group->currency, 'proration_basis' => $group->proration_basis,
            'cutoff_start_day' => $group->cutoff_start_day, 'cutoff_end_day' => $group->cutoff_end_day,
            'payment_day' => $group->payment_day, 'payment_date_adjustment' => $group->payment_date_adjustment,
        ];

        return DB::transaction(function () use ($actor, $group, $data, $calendar): PayrollPeriod {
            $period = PayrollPeriod::query()->create([
                'legal_entity_id' => $group->legal_entity_id, 'payroll_group_id' => $group->getKey(),
                'period_key' => $data['period_key'], 'period_type' => $data['period_type'],
                'payroll_start' => $data['payroll_start'], 'payroll_end' => $data['payroll_end'],
                'attendance_cutoff_start' => $data['attendance_cutoff_start'],
                'attendance_cutoff_end' => $data['attendance_cutoff_end'], 'payment_date' => $data['payment_date'],
                'status' => PayrollPeriodStatus::Open->value, 'calendar_snapshot' => $calendar,
                'created_by' => $actor->getKey(),
            ]);
            activity('payroll')->causedBy($actor)->performedOn($period)->event('payroll_period_opened')
                ->withProperties(['group_public_id' => $group->public_id, 'period_public_id' => $period->public_id,
                    'period_key' => $period->period_key, 'period_type' => $period->period_type])
                ->log('Configurable payroll period opened.');

            return $period;
        });
    }

    /** @param array<string, mixed> $data */
    public function createRun(User $actor, PayrollPeriod $period, array $data): PayrollRun
    {
        $entity = $period->legalEntity()->firstOrFail();
        $this->assertManage($actor, $entity, 'payroll.prepare');
        if (! in_array($period->periodStatus(), [PayrollPeriodStatus::Open, PayrollPeriodStatus::Processing], true)) {
            throw ValidationException::withMessages(['period' => __('payroll.validation.period_not_open')]);
        }

        return DB::transaction(function () use ($actor, $period, $data): PayrollRun {
            $version = (int) PayrollRun::query()->where('payroll_period_id', $period->getKey())
                ->where('run_type', $data['run_type'])->lockForUpdate()->max('version') + 1;
            $run = PayrollRun::query()->create([
                'legal_entity_id' => $period->legal_entity_id, 'payroll_period_id' => $period->getKey(),
                'run_type' => $data['run_type'], 'version' => $version, 'status' => PayrollRunStatus::Draft->value,
                'currency' => $period->group()->value('currency') ?: 'IDR', 'created_by' => $actor->getKey(),
            ]);
            activity('payroll')->causedBy($actor)->performedOn($run)->event('payroll_run_created')
                ->withProperties(['period_public_id' => $period->public_id, 'run_public_id' => $run->public_id,
                    'run_type' => $run->run_type, 'version' => $version])
                ->log('Versioned payroll run created.');

            return $run;
        });
    }

    public function calculate(User $actor, PayrollRun $run): PayrollRun
    {
        $entity = $run->legalEntity()->firstOrFail();
        $this->assertManage($actor, $entity, 'payroll.prepare');

        return DB::transaction(function () use ($actor, $run): PayrollRun {
            $locked = PayrollRun::query()->whereKey($run->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->runStatus() !== PayrollRunStatus::Draft || $locked->employees()->exists()) {
                throw ValidationException::withMessages(['run' => __('payroll.validation.run_not_draft')]);
            }
            $period = $locked->period()->with('group')->firstOrFail();
            $memberships = PayrollGroupMembership::query()->where('payroll_group_id', $period->payroll_group_id)
                ->overlapping($period->payrollStart()->toDateString(), $period->payrollEnd()->toDateString())
                ->with('employee')->lockForUpdate()->get()->unique('employee_id')->values();
            if ($memberships->isEmpty()) {
                $this->finding($locked, null, 'error', 'NO_EMPLOYEES', 'payroll.findings.no_employees');
            }
            $bankRecords = $this->bankRecordsFor($memberships, $period);
            $duplicateBanks = $bankRecords->filter(fn (EmployeeBankAccount $account): bool => $account->account_number_blind_index !== '')
                ->groupBy('account_number_blind_index')->filter(fn (Collection $accounts): bool => $accounts->count() > 1)
                ->keys()->all();
            $runTotals = ['gross' => '0.0000', 'deduction' => '0.0000', 'employer' => '0.0000', 'tax' => '0.0000', 'bpjs' => '0.0000', 'net' => '0.0000'];
            foreach ($memberships as $membership) {
                $employee = $membership->employee;
                if (! $employee instanceof Employee) {
                    continue;
                }
                $bank = $bankRecords->get($employee->getKey());
                $result = $this->calculateEmployee($locked, $period, $employee, $bank instanceof EmployeeBankAccount ? $bank : null,
                    $bank instanceof EmployeeBankAccount && in_array($bank->account_number_blind_index, $duplicateBanks, true));
                if ($result === null) {
                    continue;
                }
                foreach ($runTotals as $key => $total) {
                    $runTotals[$key] = DecimalMath::add($total, $result[$key]);
                }
            }
            $summary = $this->findingSummary($locked);
            $locked->update([
                'status' => PayrollRunStatus::Calculated->value, 'source_snapshot_at' => now(),
                'gross_total' => $runTotals['gross'], 'deduction_total' => $runTotals['deduction'],
                'employer_total' => $runTotals['employer'], 'tax_total' => $runTotals['tax'],
                'bpjs_total' => $runTotals['bpjs'], 'net_total' => $runTotals['net'],
                'validation_summary' => $summary, 'calculated_by' => $actor->getKey(), 'calculated_at' => now(),
            ]);
            $period->update(['status' => PayrollPeriodStatus::Processing->value]);
            activity('payroll')->causedBy($actor)->performedOn($locked)->event('payroll_run_calculated')
                ->withProperties(['run_public_id' => $locked->public_id, 'employee_count' => $locked->employees()->count(),
                    'finding_summary' => $summary, 'version' => $locked->version])
                ->log('Gross-to-net payroll snapshot calculated.');

            return $locked->refresh()->load(['period.group', 'employees.items', 'findings']);
        });
    }

    public function validateRun(User $actor, PayrollRun $run): PayrollRun
    {
        $entity = $run->legalEntity()->firstOrFail();
        $this->assertManage($actor, $entity, 'payroll.validate');

        return DB::transaction(function () use ($actor, $run): PayrollRun {
            $locked = PayrollRun::query()->whereKey($run->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->runStatus() !== PayrollRunStatus::Calculated) {
                throw ValidationException::withMessages(['run' => __('payroll.validation.run_not_calculated')]);
            }
            $summary = $this->findingSummary($locked);
            if ($summary['error'] > 0) {
                throw ValidationException::withMessages(['run' => __('payroll.validation.blocking_findings')]);
            }
            $locked->update(['status' => PayrollRunStatus::Validated->value, 'validation_summary' => $summary,
                'validated_by' => $actor->getKey(), 'validated_at' => now()]);
            activity('payroll')->causedBy($actor)->performedOn($locked)->event('payroll_run_validated')
                ->withProperties(['run_public_id' => $locked->public_id, 'finding_summary' => $summary, 'version' => $locked->version])
                ->log('Payroll validation completed without blocking findings.');

            return $locked->refresh()->load(['period.group', 'employees.items', 'findings']);
        });
    }

    /** @return array{gross:string,deduction:string,employer:string,tax:string,bpjs:string,net:string}|null */
    private function calculateEmployee(PayrollRun $run, PayrollPeriod $period, Employee $employee, ?EmployeeBankAccount $bank, bool $duplicateBank): ?array
    {
        $periodStart = $period->payrollStart();
        $periodEnd = $period->payrollEnd();
        $histories = EmploymentHistory::query()->where('employee_id', $employee->getKey())
            ->where('legal_entity_id', $run->legal_entity_id)->whereDate('effective_from', '<=', $periodEnd->toDateString())
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $periodStart->toDateString()))
            ->with(['branch', 'division', 'department', 'position', 'workLocation', 'costCenter'])->orderBy('effective_from')->get();
        if ($histories->isEmpty()) {
            $this->finding($run, null, 'error', 'MISSING_EMPLOYMENT_'.$employee->getKey(), 'payroll.findings.missing_employment', ['employee_public_id' => $employee->public_id]);

            return null;
        }
        $employment = $histories->last();
        $joinDate = CarbonImmutable::parse((string) $employment->getRawOriginal('join_date'));
        $terminationValue = $employment->getRawOriginal('termination_date');
        $termination = is_string($terminationValue) && $terminationValue !== '' ? CarbonImmutable::parse($terminationValue) : $periodEnd;
        $serviceFrom = $joinDate->greaterThan($periodStart) ? $joinDate : $periodStart;
        $serviceTo = $termination->lessThan($periodEnd) ? $termination : $periodEnd;
        if ($serviceTo->lessThan($serviceFrom)) {
            return null;
        }
        $basis = (string) $period->group?->proration_basis;
        $periodDays = $this->basisDays($employee, $periodStart, $periodEnd, $basis);
        $payableDays = $this->basisDays($employee, $serviceFrom, $serviceTo, $basis);
        if ($periodDays < 1 || $payableDays < 1) {
            $this->finding($run, null, 'error', 'NO_PAYABLE_DAYS_'.$employee->getKey(), 'payroll.findings.no_payable_days', ['employee_public_id' => $employee->public_id]);

            return null;
        }
        $salaryHistories = SalaryHistory::query()->where('employee_id', $employee->getKey())
            ->where('legal_entity_id', $run->legal_entity_id)->where('status', SalaryHistoryStatus::Approved->value)
            ->overlapping($serviceFrom->toDateString(), $serviceTo->toDateString())->with('components.component')
            ->orderBy('effective_from')->get();
        $employeeFindings = [];
        if ($salaryHistories->isEmpty()) {
            $employeeFindings[] = ['error', 'MISSING_SALARY', 'payroll.findings.missing_salary', null];
        }
        if (! $bank) {
            $employeeFindings[] = ['error', 'MISSING_BANK', 'payroll.findings.missing_bank', null];
        } elseif ($bank->verification_status !== 'verified') {
            $employeeFindings[] = ['error', 'UNVERIFIED_BANK', 'payroll.findings.unverified_bank', null];
        }
        if ($duplicateBank) {
            $employeeFindings[] = ['error', 'DUPLICATE_BANK', 'payroll.findings.duplicate_bank', null];
        }
        $attendanceBlocked = AttendanceRecord::query()->where('employee_id', $employee->getKey())
            ->where('legal_entity_id', $run->legal_entity_id)
            ->whereBetween('work_date', [$period->cutoffStart()->toDateString(), $period->cutoffEnd()->toDateString()])
            ->where('is_current', true)->where(fn (Builder $q) => $q->whereIn('status', ['incomplete', 'anomalous'])
            ->orWhere('payroll_eligibility', 'blocked'))->exists();
        if ($attendanceBlocked) {
            $employeeFindings[] = ['error', 'ATTENDANCE_BLOCKED', 'payroll.findings.attendance_blocked', null];
        }
        $items = [];
        $gross = '0.0000';
        $deduction = '0.0000';
        $employer = '0.0000';
        $salarySnapshot = [];
        foreach ($salaryHistories as $index => $salary) {
            $next = $salaryHistories->get($index + 1);
            $segmentFrom = $salary->effectiveFrom();
            if ($segmentFrom->lessThan($serviceFrom)) {
                $segmentFrom = $serviceFrom;
            }
            $segmentTo = $salary->effectiveTo() ?? $serviceTo;
            if ($next instanceof SalaryHistory) {
                $nextStart = $next->effectiveFrom()->subDay();
                if ($nextStart->lessThan($segmentTo)) {
                    $segmentTo = $nextStart;
                }
            }
            if ($segmentTo->greaterThan($serviceTo)) {
                $segmentTo = $serviceTo;
            }
            if ($segmentTo->lessThan($segmentFrom)) {
                continue;
            }
            $segmentDays = $this->basisDays($employee, $segmentFrom, $segmentTo, $basis);
            $salarySnapshot[] = ['salary_history_public_id' => $salary->public_id, 'version_checksum' => $salary->version_checksum,
                'from' => $segmentFrom->toDateString(), 'to' => $segmentTo->toDateString(), 'basis_days' => $segmentDays];
            foreach ($salary->components as $line) {
                $component = $line->component;
                if (! $component instanceof SalaryComponent || ! in_array($component->calculation_type, ['fixed_monthly', 'daily_rate'], true)) {
                    continue;
                }
                $base = DecimalMath::multiply((string) $line->amount, (string) $line->quantity);
                $unrounded = $component->calculation_type === 'fixed_monthly'
                    ? DecimalMath::multiplyRatio($base, $segmentDays, $periodDays)
                    : DecimalMath::multiplyInteger($base, $segmentDays);
                $amount = DecimalMath::round($unrounded, (int) $component->rounding_scale, (string) $component->rounding_mode);
                $items[] = $this->itemData($component, (string) $line->quantity, (string) $line->amount, $base, $unrounded, $amount,
                    'salary_history', $salary->public_id, ['segment_from' => $segmentFrom->toDateString(), 'segment_to' => $segmentTo->toDateString(), 'segment_days' => $segmentDays, 'period_days' => $periodDays], count($items) + 1);
                [$gross, $deduction, $employer] = $this->accumulate($component->componentType(), $amount, $gross, $deduction, $employer);
            }
        }
        $unpaidDays = (string) LeaveRequest::query()->where('employee_id', $employee->getKey())
            ->where('legal_entity_id', $run->legal_entity_id)->where('status', 'approved')
            ->whereBetween('start_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->whereHas('type', fn (Builder $q) => $q->where('is_paid', false))->sum('total_days');
        if (DecimalMath::compare($unpaidDays, '0') > 0) {
            $latestSalary = $salaryHistories->last();
            $unpaidLine = $latestSalary instanceof SalaryHistory
                ? $latestSalary->components->first(fn ($line): bool => $line->component?->calculation_type === 'unpaid_leave_daily') : null;
            if ($unpaidLine && $unpaidLine->component instanceof SalaryComponent) {
                $component = $unpaidLine->component;
                $unrounded = DecimalMath::multiply((string) $unpaidLine->amount, $unpaidDays);
                $amount = DecimalMath::round($unrounded, (int) $component->rounding_scale, (string) $component->rounding_mode);
                $items[] = $this->itemData($component, $unpaidDays, (string) $unpaidLine->amount, (string) $unpaidLine->amount,
                    $unrounded, $amount, 'approved_unpaid_leave', $period->period_key, ['unpaid_days' => $unpaidDays], count($items) + 1);
                [$gross, $deduction, $employer] = $this->accumulate($component->componentType(), $amount, $gross, $deduction, $employer);
            } else {
                $employeeFindings[] = ['warning', 'UNPAID_LEAVE_POLICY_MISSING', 'payroll.findings.unpaid_leave_policy_missing', ['days' => $unpaidDays]];
            }
        }
        $overtime = OvertimeCalculation::query()->where('legal_entity_id', $run->legal_entity_id)->where('payroll_eligible', true)
            ->whereHas('request', fn (Builder $q) => $q->where('employee_id', $employee->getKey())
                ->where('payroll_period_key', $period->period_key))
            ->with('request')->get();
        $meal = $overtime->sum(fn (OvertimeCalculation $calc): int => (int) $calc->meal_allowance_idr);
        $transport = $overtime->sum(fn (OvertimeCalculation $calc): int => (int) $calc->transport_allowance_idr);
        foreach ([['overtime_meal', $meal], ['overtime_transport', $transport]] as [$calculationType, $amountInt]) {
            if ($amountInt < 1) {
                continue;
            }
            $component = SalaryComponent::query()->where('legal_entity_id', $run->legal_entity_id)
                ->where('calculation_type', $calculationType)->effectiveOn($periodEnd->toDateString())->first();
            if (! $component) {
                $employeeFindings[] = ['warning', strtoupper($calculationType).'_COMPONENT_MISSING', 'payroll.findings.overtime_allowance_component_missing', ['source' => $calculationType]];

                continue;
            }
            $amount = DecimalMath::round((string) $amountInt, (int) $component->rounding_scale, (string) $component->rounding_mode);
            $items[] = $this->itemData($component, '1', $amount, $amount, $amount, $amount, 'overtime_calculation',
                $period->period_key, ['eligible_calculation_count' => $overtime->count()], count($items) + 1);
            [$gross, $deduction, $employer] = $this->accumulate($component->componentType(), $amount, $gross, $deduction, $employer);
        }
        if ($overtime->sum(fn (OvertimeCalculation $calc): int => (int) $calc->payable_minutes) > 0) {
            $employeeFindings[] = ['warning', 'OVERTIME_WAGE_PENDING_PHASE_11', 'payroll.findings.overtime_wage_pending', null];
        }
        $employeeFindings[] = ['warning', 'STATUTORY_PENDING_PHASE_11', 'payroll.findings.statutory_pending', null];
        $net = DecimalMath::subtract($gross, $deduction);
        if (DecimalMath::compare($net, '0') < 0) {
            $employeeFindings[] = ['error', 'NEGATIVE_NET_PAY', 'payroll.findings.negative_net', null];
        } elseif (DecimalMath::compare($net, '0') === 0) {
            $employeeFindings[] = ['warning', 'ZERO_NET_PAY', 'payroll.findings.zero_net', null];
        }
        $employeeSnapshot = ['employee_public_id' => $employee->public_id, 'employee_number' => $employee->employee_number,
            'full_name' => $employee->full_name, 'employment_status' => (string) $employment->getRawOriginal('employment_status'),
            'branch_public_id' => $employment->branch?->public_id, 'branch_name' => $employment->branch?->name,
            'division_public_id' => $employment->division?->public_id, 'division_name' => $employment->division?->name,
            'department_public_id' => $employment->department?->public_id, 'department_name' => $employment->department?->name,
            'position_public_id' => $employment->position?->public_id, 'position_name' => $employment->position?->name,
            'join_date' => $joinDate->toDateString(), 'termination_date' => is_string($terminationValue) ? $terminationValue : null];
        $bankSnapshot = $bank ? ['bank_code' => $bank->bank_code, 'bank_name' => $bank->bank_name,
            'account_number_last_four' => $bank->account_number_last_four, 'account_holder_name' => $bank->account_holder_name,
            'verification_status' => $bank->verification_status] : null;
        $snapshotChecksum = hash('sha256', json_encode(['employee' => $employeeSnapshot, 'bank' => $bankSnapshot,
            'salary' => $salarySnapshot, 'items' => $items, 'period_public_id' => $period->public_id], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $hasError = collect($employeeFindings)->contains(fn (array $finding): bool => $finding[0] === 'error');
        $runEmployee = PayrollRunEmployee::query()->create([
            'legal_entity_id' => $run->legal_entity_id, 'payroll_run_id' => $run->getKey(), 'employee_id' => $employee->getKey(),
            'employment_history_id' => $employment->getKey(), 'salary_history_id' => $salaryHistories->last()?->getKey(),
            'employee_snapshot' => $employeeSnapshot, 'bank_snapshot' => $bankSnapshot, 'salary_snapshot' => $salarySnapshot,
            'service_from' => $serviceFrom->toDateString(), 'service_to' => $serviceTo->toDateString(),
            'payable_days' => $payableDays, 'period_days' => $periodDays, 'gross_total' => $gross,
            'deduction_total' => $deduction, 'employer_total' => $employer, 'tax_total' => '0.0000',
            'bpjs_total' => '0.0000', 'net_total' => $net, 'validation_status' => $hasError ? 'blocked' : 'warning',
            'snapshot_checksum' => $snapshotChecksum,
        ]);
        foreach ($items as $item) {
            $runEmployee->items()->create($item + ['legal_entity_id' => $run->legal_entity_id]);
        }
        foreach ($employeeFindings as [$severity, $code, $message, $details]) {
            $this->finding($run, $runEmployee, $severity, $code, $message, $details);
        }

        return ['gross' => $gross, 'deduction' => $deduction, 'employer' => $employer, 'tax' => '0.0000', 'bpjs' => '0.0000', 'net' => $net];
    }

    /**
     * @param  Collection<int, PayrollGroupMembership>  $memberships
     * @return Collection<int, EmployeeBankAccount>
     */
    private function bankRecordsFor(Collection $memberships, PayrollPeriod $period): Collection
    {
        $employeeIds = $memberships->pluck('employee_id');
        $paymentDate = $period->paymentDate()->toDateString();

        return EmployeeBankAccount::query()->whereIn('employee_id', $employeeIds)->where('legal_entity_id', $period->legal_entity_id)
            ->whereDate('effective_from', '<=', $paymentDate)
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $paymentDate))
            ->orderByDesc('effective_from')->get()->unique('employee_id')->keyBy('employee_id');
    }

    private function basisDays(Employee $employee, CarbonImmutable $from, CarbonImmutable $to, string $basis): int
    {
        if ($basis === 'calendar_days') {
            return intdiv($to->getTimestamp() - $from->getTimestamp(), 86400) + 1;
        }
        $days = 0;
        for ($date = $from; $date->lessThanOrEqualTo($to); $date = $date->addDay()) {
            $history = EmploymentHistory::query()->where('employee_id', $employee->getKey())->effectiveOn($date->toDateString())->first();
            if (! $history) {
                $history = EmploymentHistory::query()->where('employee_id', $employee->getKey())
                    ->whereDate('effective_from', '>', $date->toDateString())->oldest('effective_from')->first();
            }
            if (! $history) {
                continue;
            }
            $holiday = Holiday::query()->where('legal_entity_id', $employee->legal_entity_id)->whereDate('holiday_date', $date->toDateString())
                ->where('status', 'active')->where(fn (Builder $q) => $q->whereNull('branch_id')->orWhere('branch_id', $history->branch_id))->exists();
            if ($holiday) {
                continue;
            }
            $assignment = EmployeeScheduleAssignment::query()->where('employee_id', $employee->getKey())
                ->effectiveOn($date->toDateString())->with('schedule')->latest('effective_from')->first();
            $schedule = $assignment?->schedule;
            if (! $schedule instanceof WorkSchedule) {
                $base = WorkSchedule::query()->where('legal_entity_id', $employee->legal_entity_id)->where('status', 'active')->effectiveOn($date->toDateString());
                $schedule = (clone $base)->where('department_id', $history->department_id)->latest('effective_from')->first()
                    ?? (clone $base)->whereNull('department_id')->where('branch_id', $history->branch_id)->latest('effective_from')->first()
                    ?? (clone $base)->whereNull('department_id')->whereNull('branch_id')->latest('effective_from')->first();
            }
            if (! $schedule instanceof WorkSchedule) {
                throw ValidationException::withMessages(['run' => __('payroll.validation.missing_schedule')]);
            }
            if ((bool) $schedule->days()->where('day_of_week', $date->dayOfWeekIso)->value('is_working_day')) {
                $days++;
            }
        }

        return $days;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function itemData(SalaryComponent $component, string $quantity, string $rate, string $base, string $unrounded, string $amount, string $sourceType, ?string $sourceReference, array $metadata, int $sequence): array
    {
        return ['salary_component_id' => $component->getKey(), 'component_code' => $component->code,
            'component_name' => $component->name, 'component_type' => $component->componentType()->value,
            'calculation_type' => $component->calculation_type, 'quantity' => DecimalMath::normalize($quantity),
            'rate' => DecimalMath::normalize($rate), 'base_amount' => DecimalMath::normalize($base),
            'unrounded_amount' => DecimalMath::normalize($unrounded), 'amount' => DecimalMath::normalize($amount),
            'currency' => $component->currency, 'source_type' => $sourceType, 'source_reference' => $sourceReference,
            'calculation_metadata' => $metadata, 'sequence' => $sequence];
    }

    /** @return array{string,string,string} */
    private function accumulate(SalaryComponentType $type, string $amount, string $gross, string $deduction, string $employer): array
    {
        return match ($type) {
            SalaryComponentType::Income => [DecimalMath::add($gross, $amount), $deduction, $employer],
            SalaryComponentType::Deduction => [$gross, DecimalMath::add($deduction, $amount), $employer],
            SalaryComponentType::Employer => [$gross, $deduction, DecimalMath::add($employer, $amount)],
        };
    }

    /** @param array<string, mixed>|null $details */
    private function finding(PayrollRun $run, ?PayrollRunEmployee $employee, string $severity, string $code, string $messageKey, ?array $details = null): PayrollValidationFinding
    {
        return PayrollValidationFinding::query()->create(['legal_entity_id' => $run->legal_entity_id,
            'payroll_run_id' => $run->getKey(), 'payroll_run_employee_id' => $employee?->getKey(), 'severity' => $severity,
            'code' => $code, 'message_key' => $messageKey, 'details' => $details, 'status' => 'open']);
    }

    /** @return array{error:int,warning:int,info:int} */
    private function findingSummary(PayrollRun $run): array
    {
        return ['error' => $run->findings()->where('severity', 'error')->count(),
            'warning' => $run->findings()->where('severity', 'warning')->count(),
            'info' => $run->findings()->where('severity', 'info')->count()];
    }

    private function assertManage(User $actor, LegalEntity $entity, string $permission): void
    {
        abort_unless($actor->can($permission) && $this->scope->manages($actor, $entity->getKey()), 403);
    }
}
