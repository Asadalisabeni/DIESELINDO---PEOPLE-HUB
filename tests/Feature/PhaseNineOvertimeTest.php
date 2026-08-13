<?php

use App\Enums\OvertimeDayType;
use App\Enums\OvertimeRequestStatus;
use App\Models\ApprovalAction;
use App\Models\ApprovalDefinition;
use App\Models\ApprovalInstanceStep;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentHistory;
use App\Models\Holiday;
use App\Models\LegalEntity;
use App\Models\OvertimeCalculation;
use App\Models\OvertimeRequest;
use App\Models\OvertimeRule;
use App\Models\Position;
use App\Models\User;
use App\Models\UserLegalEntityAccess;
use App\Models\WorkSchedule;
use App\Notifications\OvertimeApprovalPending;
use App\Notifications\OvertimeRequestReviewed;
use App\Services\Approval\ApprovalEngine;
use App\Services\Overtime\OvertimeManager;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-08-03 03:00:00');
    $this->seed(RolePermissionSeeder::class);
    Notification::fake();
});

afterEach(fn () => Carbon::setTestNow());

function phaseNineUser(string $role, ?Employee $employee = null, string $email = 'phase9@example.test'): User
{
    $user = User::factory()->create(['email' => $email, 'password' => Hash::make('ValidPassword!2026')]);
    $user->assignRole($role);
    if ($employee) {
        $user->forceFill(['employee_id' => $employee->getKey()])->save();
    }

    return $user;
}

function phaseNineScope(User $user, LegalEntity $entity, User $grantor, string $accessLevel): void
{
    UserLegalEntityAccess::query()->create([
        'user_id' => $user->getKey(), 'legal_entity_id' => $entity->getKey(), 'access_level' => $accessLevel,
        'effective_from' => '2025-01-01', 'granted_by' => $grantor->getKey(), 'reason' => 'Phase 9 test scope.',
    ]);
}

/** @return array<string, mixed> */
function phaseNineContext(string $code = 'DUN'): array
{
    $admin = phaseNineUser('Super Admin', null, strtolower($code).'.phase9.admin@example.test');
    $entity = LegalEntity::query()->create([
        'code' => $code, 'legal_name' => 'PT '.$code, 'display_name' => $code.' Company',
        'country_code' => 'ID', 'timezone' => 'Asia/Jakarta', 'currency' => 'IDR', 'status' => 'active',
        'created_by' => $admin->getKey(), 'updated_by' => $admin->getKey(),
    ]);
    phaseNineScope($admin, $entity, $admin, 'manage');
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
    $manager = Employee::query()->create([
        'legal_entity_id' => $entity->getKey(), 'employee_number' => $code.'-MGR', 'full_name' => 'Manager '.$code,
        'status' => 'active', 'created_by' => $admin->getKey(), 'updated_by' => $admin->getKey(),
    ]);
    $employee = Employee::query()->create([
        'legal_entity_id' => $entity->getKey(), 'employee_number' => $code.'-001', 'full_name' => 'Employee '.$code,
        'status' => 'active', 'created_by' => $admin->getKey(), 'updated_by' => $admin->getKey(),
    ]);
    foreach ([[$manager, null], [$employee, $manager]] as [$person, $reportsTo]) {
        EmploymentHistory::query()->create([
            'legal_entity_id' => $entity->getKey(), 'employee_id' => $person->getKey(),
            'employee_number' => $person->employee_number, 'branch_id' => $branch->getKey(),
            'department_id' => $department->getKey(), 'position_id' => $position->getKey(),
            'manager_employee_id' => $reportsTo?->getKey(), 'employment_status' => 'permanent',
            'join_date' => '2025-01-01', 'effective_from' => '2025-01-01', 'change_reason' => 'Phase 9 assignment.',
            'source' => 'manual', 'created_by' => $admin->getKey(),
        ]);
    }
    $managerUser = phaseNineUser('Supervisor', $manager, strtolower($code).'.phase9.manager@example.test');
    $employeeUser = phaseNineUser('Employee', $employee, strtolower($code).'.phase9.employee@example.test');
    $hr = phaseNineUser('Company HR Admin', null, strtolower($code).'.phase9.hr@example.test');
    $payroll = phaseNineUser('Payroll Administrator', null, strtolower($code).'.phase9.payroll@example.test');
    phaseNineScope($hr, $entity, $admin, 'manage');
    phaseNineScope($payroll, $entity, $admin, 'manage');
    $schedule = WorkSchedule::query()->create([
        'legal_entity_id' => $entity->getKey(), 'department_id' => $department->getKey(), 'code' => $code.'-REG',
        'name' => 'Phase 9 working calendar', 'timezone' => 'Asia/Jakarta', 'late_grace_minutes' => 0,
        'early_leave_grace_minutes' => 0, 'effective_from' => '2026-01-01', 'status' => 'active',
        'created_by' => $admin->getKey(),
    ]);
    foreach (range(1, 7) as $day) {
        $working = $day <= 5;
        $schedule->days()->create([
            'day_of_week' => $day, 'is_working_day' => $working, 'start_time' => $working ? '08:00' : null,
            'end_time' => $working ? '17:00' : null, 'break_minutes' => $working ? 60 : 0, 'crosses_midnight' => false,
        ]);
    }

    return compact('admin', 'hr', 'payroll', 'entity', 'branch', 'department', 'position', 'manager', 'employee', 'schedule') + [
        'manager_user' => $managerUser, 'employee_user' => $employeeUser,
    ];
}

/** @param array<string, mixed> $overrides */
function phaseNineRule(array $context, array $overrides = []): OvertimeRule
{
    return app(OvertimeManager::class)->createRule($context['hr'], $context['entity'], array_merge([
        'code' => 'WORKDAY', 'name' => 'Working day overtime', 'day_type' => 'working_day',
        'calculation_method' => 'internal', 'minimum_minutes' => 30, 'rounding_increment_minutes' => 15,
        'rounding_mode' => 'floor', 'maximum_minutes' => 240,
        'segment_rules' => [
            ['up_to_minutes' => 60, 'multiplier_hundredths' => 150],
            ['up_to_minutes' => null, 'multiplier_hundredths' => 200],
        ],
        'meal_threshold_minutes' => 120, 'meal_allowance_idr' => 25000,
        'transport_threshold_minutes' => 180, 'transport_allowance_idr' => 40000,
        'eligibility' => 'all_active', 'effective_from' => '2026-01-01', 'effective_to' => null,
    ], $overrides));
}

/** @param array<string, mixed> $overrides */
function phaseNineSubmit(array $context, array $overrides = []): OvertimeRequest
{
    return app(OvertimeManager::class)->submit($context['employee_user'], $context['employee'], array_merge([
        'request_type' => 'regular', 'planned_start' => '2026-08-10 18:00', 'planned_end' => '2026-08-10 21:00',
        'reason' => 'Month-end operational reconciliation requires controlled overtime.',
        'work_description' => 'Reconcile approved operational records and complete the documented handover.',
    ], $overrides));
}

function phaseNineAttendance(array $context, string $date = '2026-08-10', string $checkOut = '2026-08-10 20:53', bool $scheduled = true): AttendanceRecord
{
    return AttendanceRecord::query()->create([
        'legal_entity_id' => $context['entity']->getKey(), 'employee_id' => $context['employee']->getKey(),
        'work_schedule_id' => $context['schedule']->getKey(), 'work_date' => $date,
        'scheduled_start_at' => $scheduled ? Carbon::parse($date.' 08:00', 'Asia/Jakarta')->utc() : null,
        'scheduled_end_at' => $scheduled ? Carbon::parse($date.' 17:00', 'Asia/Jakarta')->utc() : null,
        'check_in_at' => Carbon::parse($date.' 08:00', 'Asia/Jakarta')->utc(),
        'check_out_at' => Carbon::parse($checkOut, 'Asia/Jakarta')->utc(), 'worked_minutes' => 773,
        'late_minutes' => 0, 'early_leave_minutes' => 0, 'overtime_minutes' => 0,
        'status' => $scheduled ? 'present' : 'holiday_attendance', 'payroll_eligibility' => 'pending_review',
        'normalization_version' => 1, 'is_current' => true, 'normalized_by' => $context['hr']->getKey(), 'normalized_at' => now(),
    ]);
}

test('phase nine schema permissions and generic approval boundary are present', function () {
    foreach (['overtime_rules', 'overtime_requests', 'overtime_calculations'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
    expect(phaseNineUser('Employee')->can('overtime.request'))->toBeTrue()
        ->and(phaseNineUser('Company HR Admin', null, 'phase9.hr.permission@example.test')->can('overtime.validate'))->toBeTrue()
        ->and(phaseNineUser('Company HR Admin', null, 'phase9.no-payroll@example.test')->can('overtime.include-payroll'))->toBeFalse()
        ->and(phaseNineUser('Payroll Administrator', null, 'phase9.payroll.permission@example.test')->can('overtime.include-payroll'))->toBeTrue();
    $approvalSource = file_get_contents(app_path('Services/Approval/ApprovalEngine.php'));
    expect($approvalSource)->not->toContain('App\\Models\\OvertimeRequest')
        ->and($approvalSource)->not->toContain('App\\Models\\LeaveRequest');
});

test('overtime rules are effective dated configurable and overlapping day policies are rejected', function () {
    $context = phaseNineContext();
    $rule = phaseNineRule($context);
    expect($rule->segment_rules)->toBe([
        ['up_to_minutes' => 60, 'multiplier_hundredths' => 150],
        ['up_to_minutes' => null, 'multiplier_hundredths' => 200],
    ])->and($rule->rounding_increment_minutes)->toBe(15)
        ->and($rule->meal_allowance_idr)->toBe(25000)
        ->and(ApprovalDefinition::query()->where('subject_type', 'overtime_request')->sole()->steps)->toHaveCount(3);

    expect(fn () => phaseNineRule($context, ['code' => 'OVERLAP', 'effective_from' => '2026-06-01']))
        ->toThrow(ValidationException::class);
});

test('overtime request must be planned and stores encrypted confidential details with queued approval notification', function () {
    $context = phaseNineContext();
    phaseNineRule($context);
    $request = phaseNineSubmit($context);
    $raw = DB::table('overtime_requests')->where('id', $request->getKey())->sole();

    expect($request->requestStatus())->toBe(OvertimeRequestStatus::PendingManager)
        ->and($request->day_type_snapshot)->toBe(OvertimeDayType::WorkingDay)
        ->and($request->planned_minutes)->toBe(180)
        ->and((string) $raw->reason)->not->toContain('Month-end operational')
        ->and((string) $raw->work_description)->not->toContain('Reconcile approved');
    Notification::assertSentTo($context['manager_user'], OvertimeApprovalPending::class,
        fn (OvertimeApprovalPending $notification, array $channels): bool => $notification->afterCommit && $channels === ['database', 'mail']);

    expect(fn () => phaseNineSubmit($context, ['planned_start' => '2026-08-02 18:00', 'planned_end' => '2026-08-02 20:00']))
        ->toThrow(ValidationException::class)
        ->and(OvertimeRequest::query()->count())->toBe(1);

    $context['employee']->update(['status' => 'inactive']);
    expect(fn () => phaseNineSubmit($context, ['planned_start' => '2026-08-11 18:00', 'planned_end' => '2026-08-11 20:00']))
        ->toThrow(ValidationException::class)
        ->and(OvertimeRequest::query()->count())->toBe(1);
});

test('manager HR and Payroll reconcile planned approved actual payable and eligibility without wage calculation', function () {
    $context = phaseNineContext();
    phaseNineRule($context);
    $request = phaseNineSubmit($context);
    app(OvertimeManager::class)->review($context['manager_user'], $request, [
        'decision' => 'approve', 'approved_minutes' => 180, 'review_notes' => 'Approved within capacity and operational requirement.',
        'idempotency_key' => 'phase9-manager',
    ]);
    expect($request->refresh()->requestStatus())->toBe(OvertimeRequestStatus::ApprovedWaitingActual)
        ->and($request->approvalInstance?->current_step_order)->toBe(2);

    Carbon::setTestNow('2026-08-11 03:00:00');
    phaseNineAttendance($context);
    app(OvertimeManager::class)->review($context['hr'], $request->refresh(), [
        'decision' => 'approve', 'review_notes' => 'Current attendance reconciled against approved overtime.',
        'idempotency_key' => 'phase9-hr',
    ]);
    $request->refresh();
    $calculation = $request->calculation()->sole();
    expect($request->requestStatus())->toBe(OvertimeRequestStatus::PendingPayroll)
        ->and($request->actual_minutes)->toBe(233)->and($request->payable_minutes)->toBe(180)
        ->and($request->weighted_minutes_hundredths)->toBe(33000)
        ->and($request->meal_eligible)->toBeTrue()->and($request->meal_allowance_idr)->toBe(25000)
        ->and($request->transport_eligible)->toBeTrue()->and($request->transport_allowance_idr)->toBe(40000)
        ->and($calculation->rule_checksum)->toHaveLength(64)
        ->and($calculation->calculation_trace['segments'])->toHaveCount(2)
        ->and(Schema::hasColumn('overtime_calculations', 'wage_amount'))->toBeFalse();
    expect(fn () => $calculation->update(['payable_minutes' => 999]))->toThrow(LogicException::class)
        ->and(fn () => $calculation->delete())->toThrow(LogicException::class);

    app(OvertimeManager::class)->review($context['payroll'], $request->refresh(), [
        'decision' => 'approve', 'review_notes' => 'Validated eligible units included as downstream payroll input.',
        'payroll_period_key' => '2026-08', 'idempotency_key' => 'phase9-payroll',
    ]);
    expect($request->refresh()->requestStatus())->toBe(OvertimeRequestStatus::PayrollEligible)
        ->and($request->payroll_period_key)->toBe('2026-08')
        ->and(ApprovalAction::query()->count())->toBe(4);
    Notification::assertSentTo($context['employee_user'], OvertimeRequestReviewed::class);
});

test('HR actual validation fails atomically until current attendance is complete', function () {
    $context = phaseNineContext();
    phaseNineRule($context);
    $request = phaseNineSubmit($context);
    app(OvertimeManager::class)->review($context['manager_user'], $request, [
        'decision' => 'approve', 'approved_minutes' => 180, 'review_notes' => 'Manager approval before planned overtime.',
    ]);
    Carbon::setTestNow('2026-08-11 03:00:00');
    expect(fn () => app(OvertimeManager::class)->review($context['hr'], $request->refresh(), [
        'decision' => 'approve', 'review_notes' => 'Attempt validation without complete actual attendance.',
    ]))->toThrow(ValidationException::class);
    expect(OvertimeCalculation::query()->count())->toBe(0)
        ->and($request->refresh()->requestStatus())->toBe(OvertimeRequestStatus::ApprovedWaitingActual)
        ->and($request->approvalInstance?->current_step_order)->toBe(2);
});

test('national holiday actual time uses effective holiday rule and transparent rounding', function () {
    $context = phaseNineContext();
    phaseNineRule($context, [
        'code' => 'HOLIDAY', 'name' => 'Holiday overtime', 'day_type' => 'national_holiday',
        'rounding_mode' => 'floor', 'maximum_minutes' => 480,
    ]);
    Holiday::query()->create([
        'legal_entity_id' => $context['entity']->getKey(), 'holiday_date' => '2026-08-12', 'name' => 'National holiday',
        'type' => 'national', 'status' => 'active', 'created_by' => $context['hr']->getKey(),
    ]);
    $request = phaseNineSubmit($context, ['planned_start' => '2026-08-12 09:00', 'planned_end' => '2026-08-12 14:00']);
    expect($request->day_type_snapshot)->toBe(OvertimeDayType::NationalHoliday);
    app(OvertimeManager::class)->review($context['manager_user'], $request, [
        'decision' => 'approve', 'approved_minutes' => 300, 'review_notes' => 'Holiday operational support approved.',
    ]);
    Carbon::setTestNow('2026-08-13 03:00:00');
    phaseNineAttendance($context, '2026-08-12', '2026-08-12 13:40', false);
    app(OvertimeManager::class)->review($context['hr'], $request->refresh(), [
        'decision' => 'approve', 'review_notes' => 'Holiday attendance reconciled and validated.',
    ]);
    expect($request->refresh()->actual_minutes)->toBe(340)
        ->and($request->payable_minutes)->toBe(300)
        ->and($request->weighted_minutes_hundredths)->toBe(57000);
});

test('supervisor-created overtime never self approves and falls back without automatic approval', function () {
    $context = phaseNineContext();
    phaseNineRule($context);
    $request = app(OvertimeManager::class)->submit($context['manager_user'], $context['employee'], [
        'request_type' => 'emergency', 'planned_start' => '2026-08-10 18:00', 'planned_end' => '2026-08-10 20:00',
        'reason' => 'Emergency operational restoration requires planned overtime.',
        'work_description' => 'Restore the approved operational service and record recovery evidence.',
    ]);
    $step = ApprovalInstanceStep::query()->where('approval_instance_id', $request->approval_instance_id)->where('step_order', 1)->sole();
    expect($step->assigned_approver_user_id)->not->toBeNull()
        ->and($step->assigned_approver_user_id)->not->toBe($context['manager_user']->getKey())
        ->and($request->requestStatus())->toBe(OvertimeRequestStatus::PendingManager);
    expect(fn () => app(OvertimeManager::class)->review($context['manager_user'], $request, [
        'decision' => 'approve', 'approved_minutes' => 120, 'review_notes' => 'Requester tries to approve their own request.',
    ]))->toThrow(HttpException::class);
});

test('overtime approval delegation and legal entity scope fail closed', function () {
    $context = phaseNineContext('DUN');
    $other = phaseNineContext('DUS');
    phaseNineRule($context);
    $delegateEmployee = Employee::query()->create([
        'legal_entity_id' => $context['entity']->getKey(), 'employee_number' => 'DUN-DEL', 'full_name' => 'Delegate DUN',
        'status' => 'active', 'created_by' => $context['admin']->getKey(), 'updated_by' => $context['admin']->getKey(),
    ]);
    $delegate = phaseNineUser('Supervisor', $delegateEmployee, 'phase9.delegate@example.test');
    app(ApprovalEngine::class)->createDelegation($context['hr'], $context['entity'], $context['manager_user'], $delegate, [
        'effective_from' => '2026-08-01', 'effective_to' => '2026-08-31', 'reason' => 'Approved temporary operational delegation.',
    ], 'overtime_request', 'overtime.approve-manager');
    $request = phaseNineSubmit($context);
    expect($request->approvalInstance?->steps->firstWhere('step_order', 1)?->assigned_approver_user_id)->toBe($delegate->getKey());
    expect(fn () => app(OvertimeManager::class)->review($other['hr'], $request, [
        'decision' => 'approve', 'approved_minutes' => 180, 'review_notes' => 'Cross entity actor must not approve this request.',
    ]))->toThrow(HttpException::class);
});

test('overtime interfaces are bilingual scoped read only and CSV export is audited', function () {
    $context = phaseNineContext();
    phaseNineRule($context);
    phaseNineSubmit($context);
    $auditor = phaseNineUser('Auditor', null, 'phase9.auditor@example.test');
    phaseNineScope($auditor, $context['entity'], $context['admin'], 'view');

    $this->actingAs($context['employee_user'])->withSession(['locale' => 'id'])->get(route('overtime.index'))
        ->assertOk()->assertSee('Ajukan lembur');
    $this->actingAs($auditor)->withSession(['locale' => 'en'])->get(route('overtime.admin.index'))
        ->assertOk()->assertSee('Read-only access')->assertDontSee('Create overtime rule');
    $this->actingAs($auditor)->post(route('overtime.admin.rules.store'), [])->assertForbidden();

    $response = $this->actingAs($context['hr'])->get(route('overtime.admin.report.export'));
    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($response->streamedContent())->toContain('Employee DUN')->not->toContain('Month-end operational')
        ->and(DB::table('activity_log')->where('event', 'overtime_report_exported')->exists())->toBeTrue();
});

test('phase nine documentation has no open placeholders', function () {
    $files = glob(base_path('docs/09-overtime/*.md')) ?: [];
    expect($files)->toHaveCount(6);
    foreach ($files as $file) {
        $contents = file_get_contents($file);
        expect($contents)->not->toBeFalse()->and(strlen((string) $contents))->toBeGreaterThan(500)
            ->and((string) $contents)->not->toMatch('/\b(TODO|TBD)\b/i');
    }
});
