<?php

use App\Enums\PayrollRunStatus;
use App\Enums\SalaryHistoryStatus;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use App\Models\EmploymentHistory;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\LegalEntity;
use App\Models\OvertimeCalculation;
use App\Models\OvertimeRequest;
use App\Models\OvertimeRule;
use App\Models\PayrollGroup;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Position;
use App\Models\SalaryComponent;
use App\Models\SalaryHistory;
use App\Models\User;
use App\Models\UserLegalEntityAccess;
use App\Models\WorkSchedule;
use App\Services\Payroll\PayrollManager;
use App\Support\Payroll\DecimalMath;
use App\Support\Security\SensitiveValue;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-08-14 03:00:00');
    $this->seed(RolePermissionSeeder::class);
});

afterEach(fn () => Carbon::setTestNow());

function phaseTenUser(string $role, string $email): User
{
    $user = User::factory()->create(['email' => $email, 'password' => Hash::make('ValidPassword!2026')]);
    $user->assignRole($role);

    return $user;
}

function phaseTenScope(User $user, LegalEntity $entity, User $grantor, string $level = 'manage'): void
{
    UserLegalEntityAccess::query()->create([
        'user_id' => $user->getKey(), 'legal_entity_id' => $entity->getKey(), 'access_level' => $level,
        'effective_from' => '2026-01-01', 'granted_by' => $grantor->getKey(), 'reason' => 'Phase 10 test scope.',
    ]);
}

/** @return array<string, mixed> */
function phaseTenContext(string $code = 'DUN', string $joinDate = '2025-01-01'): array
{
    $admin = phaseTenUser('Super Admin', strtolower($code).'.phase10.admin@example.test');
    $payroll = phaseTenUser('Payroll Administrator', strtolower($code).'.phase10.payroll@example.test');
    $finance = phaseTenUser('Finance Reviewer', strtolower($code).'.phase10.finance@example.test');
    $hr = phaseTenUser('Company HR Admin', strtolower($code).'.phase10.hr@example.test');
    $auditor = phaseTenUser('Auditor', strtolower($code).'.phase10.auditor@example.test');
    $entity = LegalEntity::query()->create([
        'code' => $code, 'legal_name' => 'PT '.$code, 'display_name' => $code.' Company', 'country_code' => 'ID',
        'timezone' => 'Asia/Jakarta', 'currency' => 'IDR', 'status' => 'active',
        'created_by' => $admin->getKey(), 'updated_by' => $admin->getKey(),
    ]);
    foreach ([[$admin, 'manage'], [$payroll, 'manage'], [$finance, 'manage'], [$hr, 'manage'], [$auditor, 'view']] as [$user, $level]) {
        phaseTenScope($user, $entity, $admin, $level);
    }
    $branch = Branch::query()->create([
        'legal_entity_id' => $entity->getKey(), 'code' => $code.'-BR', 'name' => $code.' Branch',
        'timezone' => 'Asia/Jakarta', 'status' => 'active', 'created_by' => $admin->getKey(), 'updated_by' => $admin->getKey(),
    ]);
    $department = Department::query()->create([
        'legal_entity_id' => $entity->getKey(), 'branch_id' => $branch->getKey(), 'code' => $code.'-DEP',
        'name' => $code.' Department', 'status' => 'active', 'created_by' => $admin->getKey(), 'updated_by' => $admin->getKey(),
    ]);
    $position = Position::query()->create([
        'legal_entity_id' => $entity->getKey(), 'department_id' => $department->getKey(), 'code' => $code.'-POS',
        'name' => 'Staff', 'status' => 'active', 'created_by' => $admin->getKey(), 'updated_by' => $admin->getKey(),
    ]);
    $employee = Employee::query()->create([
        'legal_entity_id' => $entity->getKey(), 'employee_number' => $code.'-001', 'full_name' => 'Employee '.$code,
        'status' => 'active', 'created_by' => $admin->getKey(), 'updated_by' => $admin->getKey(),
    ]);
    $employment = EmploymentHistory::query()->create([
        'legal_entity_id' => $entity->getKey(), 'employee_id' => $employee->getKey(), 'employee_number' => $employee->employee_number,
        'branch_id' => $branch->getKey(), 'department_id' => $department->getKey(), 'position_id' => $position->getKey(),
        'employment_status' => 'permanent', 'join_date' => $joinDate, 'effective_from' => $joinDate,
        'change_reason' => 'Phase 10 payroll assignment.', 'source' => 'manual', 'created_by' => $admin->getKey(),
    ]);

    return compact('admin', 'payroll', 'finance', 'hr', 'auditor', 'entity', 'branch', 'department', 'position', 'employee', 'employment');
}

function phaseTenBank(array $context, string $accountNumber = '1234567890', string $status = 'verified'): EmployeeBankAccount
{
    return EmployeeBankAccount::query()->create([
        'legal_entity_id' => $context['entity']->getKey(), 'employee_id' => $context['employee']->getKey(),
        'bank_code' => 'BCA', 'bank_name' => 'Bank Central Asia', 'account_number' => $accountNumber,
        'account_number_last_four' => SensitiveValue::lastFour($accountNumber),
        'account_number_blind_index' => SensitiveValue::blindIndex($accountNumber, 'employee.bank_account'),
        'account_holder_name' => $context['employee']->full_name, 'verification_status' => $status,
        'effective_from' => '2026-01-01', 'created_by' => $context['admin']->getKey(),
    ]);
}

/** @param array<string, mixed> $overrides */
function phaseTenComponent(array $context, string $code = 'BASIC', array $overrides = []): SalaryComponent
{
    return app(PayrollManager::class)->createComponent($context['payroll'], $context['entity'], array_merge([
        'code' => $code, 'name' => $code.' component', 'type' => 'income', 'calculation_type' => 'fixed_monthly',
        'taxable' => false, 'bpjs_eligible' => false, 'rounding_scale' => 0, 'rounding_mode' => 'nearest',
        'effective_from' => '2026-01-01', 'effective_to' => null,
    ], $overrides));
}

function phaseTenGroup(array $context, string $basis = 'calendar_days'): PayrollGroup
{
    return app(PayrollManager::class)->createGroup($context['payroll'], $context['entity'], [
        'code' => 'MONTHLY', 'name' => 'Monthly payroll', 'proration_basis' => $basis,
        'cutoff_start_day' => 1, 'cutoff_end_day' => 31, 'payment_day' => 31,
        'payment_date_adjustment' => 'previous_working_day',
    ]);
}

function phaseTenMembership(array $context, PayrollGroup $group): void
{
    app(PayrollManager::class)->assignMembership($context['payroll'], $group, $context['employee'], [
        'effective_from' => '2026-01-01', 'effective_to' => null, 'reason' => 'Regular monthly payroll membership.',
    ]);
}

/** @param list<array{component: SalaryComponent, amount: string, quantity?: string}> $components */
function phaseTenSalary(array $context, array $components, string $effectiveFrom = '2026-01-01'): SalaryHistory
{
    $history = app(PayrollManager::class)->createSalary($context['payroll'], $context['employee'], [
        'effective_from' => $effectiveFrom, 'effective_to' => null,
        'reason' => 'Approved compensation change supported by confidential HR evidence.',
        'components' => array_map(static fn (array $line): array => [
            'component_public_id' => $line['component']->public_id, 'amount' => $line['amount'],
            'quantity' => $line['quantity'] ?? '1',
        ], $components),
    ]);

    return app(PayrollManager::class)->approveSalary($context['finance'], $history);
}

function phaseTenPeriod(array $context, PayrollGroup $group): PayrollPeriod
{
    return app(PayrollManager::class)->createPeriod($context['payroll'], $group, [
        'period_key' => '2026-08', 'period_type' => 'monthly', 'payroll_start' => '2026-08-01',
        'payroll_end' => '2026-08-31', 'attendance_cutoff_start' => '2026-08-01',
        'attendance_cutoff_end' => '2026-08-31', 'payment_date' => '2026-08-31',
    ]);
}

function phaseTenRun(array $context, PayrollPeriod $period): PayrollRun
{
    return app(PayrollManager::class)->createRun($context['payroll'], $period, ['run_type' => 'regular']);
}

test('phase ten schema and least privilege permissions are present', function () {
    foreach (['salary_components', 'salary_histories', 'employee_salary_components', 'payroll_groups',
        'payroll_group_memberships', 'payroll_periods', 'payroll_runs', 'payroll_run_employees',
        'payroll_items', 'payroll_validation_findings'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
    expect(phaseTenUser('Company HR Admin', 'phase10.permission.hr@example.test')->can('payroll.prepare'))->toBeFalse()
        ->and(phaseTenUser('Payroll Administrator', 'phase10.permission.payroll@example.test')->can('payroll.prepare'))->toBeTrue()
        ->and(phaseTenUser('Payroll Administrator', 'phase10.permission.validator@example.test')->can('payroll.validate'))->toBeTrue()
        ->and(phaseTenUser('Payroll Administrator', 'phase10.permission.no-review@example.test')->can('payroll.review'))->toBeFalse()
        ->and(phaseTenUser('Finance Reviewer', 'phase10.permission.finance@example.test')->can('salaries.approve'))->toBeTrue()
        ->and(phaseTenUser('Finance Reviewer', 'phase10.permission.no-prepare@example.test')->can('payroll.prepare'))->toBeFalse();

    $super = phaseTenUser('Super Admin', 'phase10.unscoped.super@example.test');
    $entity = LegalEntity::query()->create([
        'code' => 'NOS', 'legal_name' => 'PT No Scope', 'display_name' => 'No Scope', 'country_code' => 'ID',
        'timezone' => 'Asia/Jakarta', 'currency' => 'IDR', 'status' => 'active', 'created_by' => $super->getKey(), 'updated_by' => $super->getKey(),
    ]);
    expect(fn () => app(PayrollManager::class)->createComponent($super, $entity, [
        'code' => 'DENIED', 'name' => 'Denied', 'type' => 'income', 'calculation_type' => 'fixed_monthly',
        'rounding_scale' => 0, 'rounding_mode' => 'nearest', 'effective_from' => '2026-01-01',
    ]))->toThrow(HttpException::class);
});

test('decimal payroll arithmetic is exact at scale four without floats', function () {
    expect(DecimalMath::normalize('001.2'))->toBe('1.2000')
        ->and(DecimalMath::add('0.1000', '0.2000'))->toBe('0.3000')
        ->and(DecimalMath::subtract('10000000.0000', '0.0001'))->toBe('9999999.9999')
        ->and(DecimalMath::multiply('123.4567', '2.0000'))->toBe('246.9134')
        ->and(DecimalMath::multiplyRatio('31000000', 16, 31))->toBe('16000000.0000')
        ->and(DecimalMath::round('123.5555', 0, 'nearest'))->toBe('124.0000')
        ->and(DecimalMath::round('123.0001', 0, 'ceil'))->toBe('124.0000')
        ->and(DecimalMath::round('123.9999', 0, 'floor'))->toBe('123.0000')
        ->and(fn () => DecimalMath::normalize('1.00001'))->toThrow(InvalidArgumentException::class);
});

test('effective component and membership overlaps are rejected and entity scope fails closed', function () {
    $context = phaseTenContext();
    phaseTenComponent($context);
    expect(fn () => phaseTenComponent($context, 'BASIC', ['effective_from' => '2026-06-01']))
        ->toThrow(ValidationException::class);

    $group = phaseTenGroup($context);
    phaseTenMembership($context, $group);
    expect(fn () => app(PayrollManager::class)->assignMembership($context['payroll'], $group, $context['employee'], [
        'effective_from' => '2026-08-01', 'effective_to' => null, 'reason' => 'Overlapping membership.',
    ]))->toThrow(ValidationException::class);

    $other = phaseTenContext('DUS');
    expect(fn () => app(PayrollManager::class)->assignMembership($context['payroll'], $group, $other['employee'], [
        'effective_from' => '2026-01-01', 'effective_to' => null, 'reason' => 'Cross entity membership.',
    ]))->toThrow(HttpException::class);
});

test('salary versions enforce maker checker encrypted reasons checksums and immutability', function () {
    $context = phaseTenContext();
    $basic = phaseTenComponent($context);
    $draft = app(PayrollManager::class)->createSalary($context['admin'], $context['employee'], [
        'effective_from' => '2026-01-01', 'effective_to' => null, 'reason' => 'Confidential remuneration evidence.',
        'components' => [['component_public_id' => $basic->public_id, 'amount' => '31000000', 'quantity' => '1']],
    ]);
    $raw = DB::table('salary_histories')->where('id', $draft->getKey())->sole();
    expect($draft->historyStatus())->toBe(SalaryHistoryStatus::Draft)
        ->and($draft->version_checksum)->toHaveLength(64)
        ->and($draft->monthly_income_total)->toBe('31000000.0000')
        ->and((string) $raw->reason)->not->toContain('Confidential remuneration');
    expect(fn () => app(PayrollManager::class)->approveSalary($context['admin'], $draft))
        ->toThrow(ValidationException::class);

    $approved = app(PayrollManager::class)->approveSalary($context['finance'], $draft);
    expect($approved->historyStatus())->toBe(SalaryHistoryStatus::Approved)
        ->and((int) $approved->approved_by)->toBe((int) $context['finance']->getKey());
    expect(fn () => $approved->update(['monthly_income_total' => '1.0000']))->toThrow(LogicException::class)
        ->and(fn () => $approved->components()->firstOrFail()->update(['amount' => '1.0000']))->toThrow(LogicException::class)
        ->and(fn () => $approved->delete())->toThrow(LogicException::class);
});

test('monthly gross to net creates encrypted immutable snapshots and validates with explicit phase eleven warning', function () {
    $context = phaseTenContext();
    phaseTenBank($context);
    $basic = phaseTenComponent($context);
    $deduction = phaseTenComponent($context, 'COOP', ['name' => 'Cooperative deduction', 'type' => 'deduction']);
    phaseTenSalary($context, [
        ['component' => $basic, 'amount' => '31000000'], ['component' => $deduction, 'amount' => '1000000'],
    ]);
    $group = phaseTenGroup($context);
    phaseTenMembership($context, $group);
    $run = phaseTenRun($context, phaseTenPeriod($context, $group));
    $calculated = app(PayrollManager::class)->calculate($context['payroll'], $run);
    $snapshot = $calculated->employees()->with('items')->sole();
    $raw = DB::table('payroll_run_employees')->where('id', $snapshot->getKey())->sole();

    expect($calculated->gross_total)->toBe('31000000.0000')
        ->and($calculated->deduction_total)->toBe('1000000.0000')
        ->and($calculated->tax_total)->toBe('0.0000')->and($calculated->bpjs_total)->toBe('0.0000')
        ->and($calculated->net_total)->toBe('30000000.0000')
        ->and($snapshot->payable_days)->toBe(31)->and($snapshot->period_days)->toBe(31)
        ->and($snapshot->items)->toHaveCount(2)->and($snapshot->snapshot_checksum)->toHaveLength(64)
        ->and((string) $raw->bank_snapshot)->not->toContain('7890')
        ->and($snapshot->bank_snapshot['account_number_last_four'])->toBe('7890')
        ->and($calculated->findings()->where('code', 'STATUTORY_PENDING_PHASE_11')->exists())->toBeTrue();

    $validated = app(PayrollManager::class)->validateRun($context['payroll'], $calculated);
    expect($validated->runStatus())->toBe(PayrollRunStatus::Validated)
        ->and($validated->validation_summary)->toBe(['error' => 0, 'warning' => 1, 'info' => 0]);
    expect(fn () => $snapshot->update(['net_total' => '1.0000']))->toThrow(LogicException::class)
        ->and(fn () => $snapshot->items->firstOrFail()->update(['amount' => '1.0000']))->toThrow(LogicException::class)
        ->and(fn () => $validated->update(['net_total' => '1.0000']))->toThrow(LogicException::class);
});

test('joiner and mid period salary versions prorate by exact calendar service days', function () {
    $joiner = phaseTenContext('JON', '2026-08-16');
    phaseTenBank($joiner);
    $joinerBasic = phaseTenComponent($joiner);
    phaseTenSalary($joiner, [['component' => $joinerBasic, 'amount' => '31000000']], '2026-08-16');
    $joinerGroup = phaseTenGroup($joiner);
    phaseTenMembership($joiner, $joinerGroup);
    $joinerRun = app(PayrollManager::class)->calculate($joiner['payroll'], phaseTenRun($joiner, phaseTenPeriod($joiner, $joinerGroup)));
    expect($joinerRun->gross_total)->toBe('16000000.0000')
        ->and($joinerRun->employees()->sole()->payable_days)->toBe(16);

    $changed = phaseTenContext('CHG');
    phaseTenBank($changed);
    $changedBasic = phaseTenComponent($changed);
    phaseTenSalary($changed, [['component' => $changedBasic, 'amount' => '31000000']], '2026-01-01');
    phaseTenSalary($changed, [['component' => $changedBasic, 'amount' => '62000000']], '2026-08-16');
    $changedGroup = phaseTenGroup($changed);
    phaseTenMembership($changed, $changedGroup);
    $changedRun = app(PayrollManager::class)->calculate($changed['payroll'], phaseTenRun($changed, phaseTenPeriod($changed, $changedGroup)));
    expect($changedRun->gross_total)->toBe('47000000.0000')
        ->and($changedRun->employees()->sole()->salary_snapshot)->toHaveCount(2);
});

test('working day proration resolves the effective schedule without weekend assumptions', function () {
    $context = phaseTenContext('WRK', '2026-08-17');
    phaseTenBank($context);
    $schedule = WorkSchedule::query()->create([
        'legal_entity_id' => $context['entity']->getKey(), 'department_id' => $context['department']->getKey(),
        'code' => 'WRK-REG', 'name' => 'Effective payroll work schedule', 'timezone' => 'Asia/Jakarta',
        'late_grace_minutes' => 0, 'early_leave_grace_minutes' => 0, 'effective_from' => '2026-01-01',
        'status' => 'active', 'created_by' => $context['admin']->getKey(),
    ]);
    foreach (range(1, 7) as $day) {
        $working = $day <= 5;
        $schedule->days()->create([
            'day_of_week' => $day, 'is_working_day' => $working, 'start_time' => $working ? '08:00' : null,
            'end_time' => $working ? '17:00' : null, 'break_minutes' => $working ? 60 : 0, 'crosses_midnight' => false,
        ]);
    }
    $basic = phaseTenComponent($context);
    phaseTenSalary($context, [['component' => $basic, 'amount' => '21000000']], '2026-08-17');
    $group = phaseTenGroup($context, 'working_days');
    phaseTenMembership($context, $group);
    $run = app(PayrollManager::class)->calculate($context['payroll'], phaseTenRun($context, phaseTenPeriod($context, $group)));

    $snapshot = $run->employees()->with('items')->sole();
    expect($snapshot->period_days)->toBe(21)
        ->and($snapshot->payable_days)->toBe(11)
        ->and($snapshot->items->firstOrFail()->calculation_metadata['segment_days'])->toBe(11)
        ->and($run->gross_total)->toBe('11000000.0000');
});

test('approved unpaid leave and payroll eligible overtime allowances become traceable configured items', function () {
    $context = phaseTenContext('INP');
    phaseTenBank($context);
    $basic = phaseTenComponent($context);
    $unpaid = phaseTenComponent($context, 'UNPAID', ['type' => 'deduction', 'calculation_type' => 'unpaid_leave_daily']);
    phaseTenComponent($context, 'OTMEAL', ['calculation_type' => 'overtime_meal']);
    phaseTenComponent($context, 'OTTRANS', ['calculation_type' => 'overtime_transport']);
    phaseTenSalary($context, [
        ['component' => $basic, 'amount' => '31000000'], ['component' => $unpaid, 'amount' => '100000'],
    ]);
    $leaveType = LeaveType::query()->create([
        'legal_entity_id' => $context['entity']->getKey(), 'code' => 'UNPAID', 'name' => 'Unpaid leave',
        'category' => 'unpaid_leave', 'is_paid' => false, 'requires_balance' => false, 'unit' => 'day',
        'requires_payroll_confirmation' => true, 'status' => 'active', 'created_by' => $context['hr']->getKey(),
    ]);
    LeaveRequest::query()->create([
        'legal_entity_id' => $context['entity']->getKey(), 'employee_id' => $context['employee']->getKey(),
        'leave_type_id' => $leaveType->getKey(), 'requested_by' => $context['payroll']->getKey(),
        'start_date' => '2026-08-05', 'end_date' => '2026-08-06', 'total_days' => '2.00',
        'reason' => 'Approved unpaid leave evidence.', 'is_paid_snapshot' => false,
        'requires_balance_snapshot' => false, 'status' => 'approved',
        'request_fingerprint' => hash('sha256', 'phase10-unpaid-input'), 'submitted_at' => now(), 'approved_at' => now(),
    ]);
    $attendance = AttendanceRecord::query()->create([
        'legal_entity_id' => $context['entity']->getKey(), 'employee_id' => $context['employee']->getKey(),
        'work_date' => '2026-08-10', 'status' => 'present', 'payroll_eligibility' => 'eligible',
        'normalization_version' => 1, 'is_current' => true, 'normalized_at' => now(),
    ]);
    $rule = OvertimeRule::query()->create([
        'legal_entity_id' => $context['entity']->getKey(), 'code' => 'INPUT', 'name' => 'Approved overtime input',
        'day_type' => 'working_day', 'calculation_method' => 'internal', 'minimum_minutes' => 0,
        'rounding_increment_minutes' => 1, 'rounding_mode' => 'floor', 'maximum_minutes' => 240,
        'segment_rules' => [['up_to_minutes' => null, 'multiplier_hundredths' => 100]],
        'meal_threshold_minutes' => 120, 'meal_allowance_idr' => 25000,
        'transport_threshold_minutes' => 180, 'transport_allowance_idr' => 40000,
        'eligibility' => 'all_active', 'effective_from' => '2026-01-01', 'status' => 'active',
        'approved_by' => $context['hr']->getKey(), 'approved_at' => now(), 'created_by' => $context['hr']->getKey(),
    ]);
    $request = OvertimeRequest::query()->create([
        'legal_entity_id' => $context['entity']->getKey(), 'employee_id' => $context['employee']->getKey(),
        'overtime_rule_id' => $rule->getKey(), 'requested_by' => $context['payroll']->getKey(),
        'request_type' => 'regular', 'day_type_snapshot' => 'working_day', 'work_date' => '2026-08-10',
        'planned_start_at' => '2026-08-10 11:00:00', 'planned_end_at' => '2026-08-10 14:00:00',
        'planned_minutes' => 180, 'approved_minutes' => 180, 'attendance_record_id' => $attendance->getKey(),
        'actual_start_at' => '2026-08-10 11:00:00', 'actual_end_at' => '2026-08-10 14:00:00',
        'actual_minutes' => 180, 'payable_minutes' => 180, 'weighted_minutes_hundredths' => 18000,
        'meal_eligible' => true, 'meal_allowance_idr' => 25000, 'transport_eligible' => true,
        'transport_allowance_idr' => 40000, 'reason' => 'Approved input reason.',
        'work_description' => 'Approved overtime input work.', 'status' => 'payroll_eligible',
        'request_fingerprint' => hash('sha256', 'phase10-overtime-input'), 'payroll_period_key' => '2026-08',
        'submitted_at' => now(), 'approved_at' => now(), 'validated_at' => now(), 'payroll_eligible_at' => now(),
    ]);
    OvertimeCalculation::query()->create([
        'legal_entity_id' => $context['entity']->getKey(), 'overtime_request_id' => $request->getKey(),
        'overtime_rule_id' => $rule->getKey(), 'attendance_record_id' => $attendance->getKey(),
        'planned_minutes' => 180, 'approved_minutes' => 180, 'actual_minutes' => 180, 'payable_minutes' => 180,
        'weighted_minutes_hundredths' => 18000, 'meal_eligible' => true, 'meal_allowance_idr' => 25000,
        'transport_eligible' => true, 'transport_allowance_idr' => 40000, 'payroll_eligible' => true,
        'rule_snapshot' => ['code' => 'INPUT'], 'calculation_trace' => ['source' => 'phase10_test'],
        'rule_checksum' => hash('sha256', 'phase10-overtime-rule'), 'calculated_by' => $context['payroll']->getKey(),
        'calculated_at' => now(),
    ]);
    $group = phaseTenGroup($context);
    phaseTenMembership($context, $group);
    $run = app(PayrollManager::class)->calculate($context['payroll'], phaseTenRun($context, phaseTenPeriod($context, $group)));
    $snapshot = $run->employees()->with('items')->sole();

    expect($run->gross_total)->toBe('31065000.0000')->and($run->deduction_total)->toBe('200000.0000')
        ->and($run->net_total)->toBe('30865000.0000')->and($snapshot->items)->toHaveCount(4)
        ->and($snapshot->items->pluck('source_type')->all())->toContain('approved_unpaid_leave', 'overtime_calculation')
        ->and($run->findings()->where('code', 'OVERTIME_WAGE_PENDING_PHASE_11')->exists())->toBeTrue();
});

test('blocking bank and attendance evidence prevents validation and corrected sources require a new run version', function () {
    $context = phaseTenContext();
    $bank = phaseTenBank($context, '9988776655', 'pending');
    $basic = phaseTenComponent($context);
    phaseTenSalary($context, [['component' => $basic, 'amount' => '31000000']]);
    $group = phaseTenGroup($context);
    phaseTenMembership($context, $group);
    $period = phaseTenPeriod($context, $group);
    AttendanceRecord::query()->create([
        'legal_entity_id' => $context['entity']->getKey(), 'employee_id' => $context['employee']->getKey(),
        'work_date' => '2026-08-10', 'status' => 'anomalous', 'payroll_eligibility' => 'blocked',
        'normalization_version' => 1, 'is_current' => true, 'normalized_at' => now(),
    ]);
    $first = app(PayrollManager::class)->calculate($context['payroll'], phaseTenRun($context, $period));
    expect($first->validation_summary['error'])->toBe(2)
        ->and($first->findings()->whereIn('code', ['UNVERIFIED_BANK', 'ATTENDANCE_BLOCKED'])->count())->toBe(2);
    expect(fn () => app(PayrollManager::class)->validateRun($context['payroll'], $first))
        ->toThrow(ValidationException::class)
        ->and($first->refresh()->runStatus())->toBe(PayrollRunStatus::Calculated);

    $bank->update(['verification_status' => 'verified']);
    AttendanceRecord::query()->where('employee_id', $context['employee']->getKey())->update([
        'status' => 'present', 'payroll_eligibility' => 'eligible',
    ]);
    $second = phaseTenRun($context, $period->refresh());
    expect($second->version)->toBe(2);
    $second = app(PayrollManager::class)->calculate($context['payroll'], $second);
    expect($second->validation_summary['error'])->toBe(0)
        ->and(app(PayrollManager::class)->validateRun($context['payroll'], $second)->runStatus())->toBe(PayrollRunStatus::Validated);
});

test('payroll pages are bilingual read only scoped and salary only viewers cannot force run details', function () {
    $context = phaseTenContext();
    phaseTenBank($context);
    $basic = phaseTenComponent($context);
    $salary = phaseTenSalary($context, [['component' => $basic, 'amount' => '31000000']]);
    $group = phaseTenGroup($context);
    phaseTenMembership($context, $group);
    $run = app(PayrollManager::class)->calculate($context['payroll'], phaseTenRun($context, phaseTenPeriod($context, $group)));

    $this->actingAs($context['auditor'])->withSession(['locale' => 'id'])->get(route('payroll.admin.index'))
        ->assertOk()->assertSee('Akses payroll read-only')->assertDontSee('Buat komponen gaji');
    $this->actingAs($context['auditor'])->withSession(['locale' => 'en'])->get(route('payroll.admin.index', ['run' => $run->public_id]))
        ->assertOk()->assertSee('Validation findings');
    $this->actingAs($context['hr'])->get(route('payroll.admin.index', ['run' => $run->public_id]))->assertForbidden();
    $this->actingAs($context['hr'])->withSession(['locale' => 'en'])->get(route('payroll.admin.index'))->assertOk()->assertDontSee('Payroll runs');

    $response = $this->actingAs($context['auditor'])->get(route('payroll.admin.runs.export', $run->public_id));
    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($response->streamedContent())->toContain('Employee DUN')->not->toContain('1234567890')
        ->not->toContain('Approved compensation change')
        ->and(DB::table('activity_log')->where('event', 'payroll_register_exported')->exists())->toBeTrue()
        ->and($salary->reason)->toContain('Approved compensation change');
});

test('phase ten documentation has no open placeholders', function () {
    $files = glob(base_path('docs/10-payroll-foundation/*.md')) ?: [];
    expect($files)->toHaveCount(6);
    foreach ($files as $file) {
        $contents = file_get_contents($file);
        expect($contents)->not->toBeFalse()->and(strlen((string) $contents))->toBeGreaterThan(500)
            ->and((string) $contents)->not->toMatch('/\b(TODO|TBD)\b/i');
    }
});
