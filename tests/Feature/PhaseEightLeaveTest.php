<?php

use App\Enums\ApprovalInstanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Models\ApprovalAction;
use App\Models\ApprovalDefinition;
use App\Models\ApprovalDelegation;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentHistory;
use App\Models\Holiday;
use App\Models\LeaveEntitlement;
use App\Models\LeaveLedgerEntry;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\LegalEntity;
use App\Models\Position;
use App\Models\User;
use App\Models\UserLegalEntityAccess;
use App\Models\WorkSchedule;
use App\Notifications\LeaveApprovalPending;
use App\Notifications\LeaveRequestReviewed;
use App\Services\Approval\ApprovalEngine;
use App\Services\Leave\LeaveManager;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

function phaseEightUser(string $role, ?Employee $employee = null, string $email = 'phase8@example.test'): User
{
    $user = User::factory()->create(['email' => $email, 'password' => Hash::make('ValidPassword!2026')]);
    $user->assignRole($role);
    if ($employee) {
        $user->forceFill(['employee_id' => $employee->getKey()])->save();
    }

    return $user;
}

/** @return array{admin: User, hr: User, payroll: User, entity: LegalEntity, branch: Branch, department: Department, position: Position, manager: Employee, employee: Employee, manager_user: User, employee_user: User, schedule: WorkSchedule} */
function phaseEightContext(string $code = 'DUN'): array
{
    $admin = phaseEightUser('Super Admin', null, strtolower($code).'.phase8.admin@example.test');
    $entity = LegalEntity::query()->create([
        'code' => $code, 'legal_name' => 'PT '.$code, 'display_name' => $code.' Company',
        'country_code' => 'ID', 'timezone' => 'Asia/Jakarta', 'currency' => 'IDR', 'status' => 'active',
        'created_by' => $admin->getKey(), 'updated_by' => $admin->getKey(),
    ]);
    phaseEightScope($admin, $entity, $admin, 'manage');
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
            'join_date' => '2025-01-01', 'effective_from' => '2025-01-01', 'change_reason' => 'Phase 8 assignment.',
            'source' => 'manual', 'created_by' => $admin->getKey(),
        ]);
    }
    $managerUser = phaseEightUser('Supervisor', $manager, strtolower($code).'.phase8.manager@example.test');
    $employeeUser = phaseEightUser('Employee', $employee, strtolower($code).'.phase8.employee@example.test');
    $hr = phaseEightUser('Company HR Admin', null, strtolower($code).'.phase8.hr@example.test');
    $payroll = phaseEightUser('Payroll Administrator', null, strtolower($code).'.phase8.payroll@example.test');
    phaseEightScope($hr, $entity, $admin, 'manage');
    phaseEightScope($payroll, $entity, $admin, 'manage');
    $schedule = WorkSchedule::query()->create([
        'legal_entity_id' => $entity->getKey(), 'department_id' => $department->getKey(), 'code' => $code.'-REG',
        'name' => 'Phase 8 working calendar', 'timezone' => 'Asia/Jakarta', 'late_grace_minutes' => 0,
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

function phaseEightScope(User $user, LegalEntity $entity, User $grantor, string $accessLevel): void
{
    UserLegalEntityAccess::query()->create([
        'user_id' => $user->getKey(), 'legal_entity_id' => $entity->getKey(), 'access_level' => $accessLevel,
        'effective_from' => '2025-01-01', 'granted_by' => $grantor->getKey(), 'reason' => 'Phase 8 test scope.',
    ]);
}

/** @param array<string, mixed> $overrides */
function phaseEightLeaveType(array $context, array $overrides = []): LeaveType
{
    return app(LeaveManager::class)->createType($context['hr'], $context['entity'], array_merge([
        'code' => 'ANNUAL', 'name' => 'Annual leave', 'category' => 'leave', 'is_paid' => true,
        'requires_balance' => true, 'evidence_required_from_days' => null, 'requires_payroll_confirmation' => false,
        'eligibility_months' => 12, 'entitlement_quantity' => '12.00', 'validity_months' => 12,
        'carry_forward_enabled' => false, 'carry_forward_limit' => null, 'minimum_notice_days' => 0,
        'maximum_request_days' => '12.00', 'effective_from' => '2026-01-01', 'effective_to' => null,
        'approval_reminder_hours' => 24, 'approval_escalation_hours' => 72,
    ], $overrides));
}

function phaseEightEntitlement(array $context, LeaveType $type, string $quantity = '12.00', string $reference = 'ANNUAL-2026'): LeaveEntitlement
{
    return app(LeaveManager::class)->grantEntitlement($context['hr'], $context['employee'], $type, [
        'grant_reference' => $reference, 'valid_from' => '2026-01-01', 'valid_to' => '2026-12-31',
        'quantity' => $quantity, 'source' => 'entitlement', 'reason' => 'Approved Phase 8 entitlement grant.',
    ]);
}

test('phase eight schema and least privilege permissions are present', function () {
    foreach ([
        'leave_types', 'leave_policies', 'leave_entitlements', 'leave_ledger_entries', 'leave_requests',
        'approval_definitions', 'approval_steps', 'approval_instances', 'approval_instance_steps',
        'approval_actions', 'approval_delegations',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
    expect(phaseEightUser('Employee')->can('leave.request'))->toBeTrue()
        ->and(phaseEightUser('Employee', null, 'phase8.no-manage@example.test')->can('leave.manage'))->toBeFalse()
        ->and(phaseEightUser('Company HR Admin', null, 'phase8.manage@example.test')->can('leave.adjust'))->toBeTrue()
        ->and(phaseEightUser('Payroll Administrator', null, 'phase8.payroll.permission@example.test')->can('leave.confirm-payroll'))->toBeTrue();
});

test('leave policy is effective dated configurable and rejects overlapping versions', function () {
    $context = phaseEightContext();
    $type = phaseEightLeaveType($context);
    $policy = $type->policies()->sole();
    $definition = ApprovalDefinition::query()->where('key', 'leave.annual')->sole();

    expect($policy->eligibility_months)->toBe(12)
        ->and($policy->entitlement_quantity)->toBe('12.00')
        ->and($policy->validity_months)->toBe(12)
        ->and($policy->carry_forward_enabled)->toBeFalse()
        ->and($policy->approved_at)->not->toBeNull()
        ->and($definition->reminder_after_hours)->toBe(24)
        ->and($definition->escalation_after_hours)->toBe(72);
    expect(fn () => app(LeaveManager::class)->createPolicy($context['hr'], $type, [
        'eligibility_months' => 0, 'entitlement_quantity' => '10.00', 'validity_months' => 6,
        'carry_forward_enabled' => false, 'carry_forward_limit' => null, 'minimum_notice_days' => 0,
        'maximum_request_days' => null, 'effective_from' => '2026-06-01', 'effective_to' => null,
    ]))->toThrow(ValidationException::class);
});

test('entitlement grant is idempotent and balance is derived from immutable ledger entries', function () {
    $context = phaseEightContext();
    $type = phaseEightLeaveType($context);
    $first = phaseEightEntitlement($context, $type);
    $second = phaseEightEntitlement($context, $type);
    $adjustment = app(LeaveManager::class)->adjust($context['hr'], $first, [
        'quantity' => '-1.25', 'effective_date' => '2026-08-03', 'reason' => 'Audited correction to migrated opening balance.',
        'reference_key' => 'ADJ-2026-001',
    ]);

    expect($second->is($first))->toBeTrue()
        ->and(LeaveEntitlement::query()->count())->toBe(1)
        ->and(LeaveLedgerEntry::query()->count())->toBe(2)
        ->and($first->refresh()->balance())->toBe('10.75');
    expect(fn () => $adjustment->update(['quantity' => '99.00']))->toThrow(LogicException::class)
        ->and(fn () => $adjustment->delete())->toThrow(LogicException::class);
});

test('working calendar and holiday determine request days before manager and HR approval post usage', function () {
    $context = phaseEightContext();
    $type = phaseEightLeaveType($context);
    $entitlement = phaseEightEntitlement($context, $type);
    Holiday::query()->create([
        'legal_entity_id' => $context['entity']->getKey(), 'holiday_date' => '2026-08-11', 'name' => 'Company holiday',
        'type' => 'company', 'status' => 'active', 'created_by' => $context['hr']->getKey(),
    ]);
    $request = app(LeaveManager::class)->submit($context['employee_user'], $context['employee'], $type, [
        'start_date' => '2026-08-10', 'end_date' => '2026-08-12', 'reason' => 'Planned annual leave with handover completed.',
    ]);
    $rawRequest = DB::table('leave_requests')->where('id', $request->getKey())->sole();
    $rawInstance = DB::table('approval_instances')->where('id', $request->approval_instance_id)->sole();

    expect($request->total_days)->toBe('2.00')
        ->and($request->requestStatus())->toBe(LeaveRequestStatus::PendingManager)
        ->and((string) $rawRequest->reason)->not->toContain('Planned annual leave')
        ->and((string) $rawInstance->subject_snapshot)->not->toContain($context['employee']->public_id);
    Notification::assertSentTo(
        $context['manager_user'],
        LeaveApprovalPending::class,
        fn (LeaveApprovalPending $notification, array $channels): bool => $notification->afterCommit
            && $channels === ['database', 'mail'],
    );
    app(LeaveManager::class)->review($context['manager_user'], $request, [
        'decision' => 'approve', 'review_notes' => 'Handover and team capacity confirmed.', 'idempotency_key' => 'manager-approval-1',
    ]);
    expect($request->refresh()->requestStatus())->toBe(LeaveRequestStatus::PendingHr);
    app(LeaveManager::class)->review($context['hr'], $request->refresh(), [
        'decision' => 'approve', 'review_notes' => 'Policy, balance, and calendar validated.', 'idempotency_key' => 'hr-approval-1',
    ]);

    $request->refresh();
    expect($request->requestStatus())->toBe(LeaveRequestStatus::Approved)
        ->and($request->approvalInstance?->instanceStatus())->toBe(ApprovalInstanceStatus::Approved)
        ->and($entitlement->refresh()->balance())->toBe('10.00')
        ->and(LeaveLedgerEntry::query()->where('leave_request_id', $request->getKey())->value('quantity'))->toBe('-2.00')
        ->and(ApprovalAction::query()->count())->toBe(3);
    Notification::assertSentTo(
        $context['employee_user'],
        LeaveRequestReviewed::class,
        fn (LeaveRequestReviewed $notification, array $channels): bool => $notification->afterCommit
            && $channels === ['database', 'mail'],
    );
    $action = ApprovalAction::query()->where('action', 'approve')->firstOrFail();
    expect(fn () => $action->delete())->toThrow(LogicException::class);
});

test('unpaid leave requires payroll confirmation after manager and HR validation without balance usage', function () {
    $context = phaseEightContext();
    $type = phaseEightLeaveType($context, [
        'code' => 'UNPAID', 'name' => 'Unpaid leave', 'category' => 'unpaid', 'is_paid' => false,
        'requires_balance' => false, 'requires_payroll_confirmation' => true, 'eligibility_months' => 0,
        'entitlement_quantity' => '0.00', 'validity_months' => null, 'maximum_request_days' => '30.00',
    ]);
    $request = app(LeaveManager::class)->submit($context['employee_user'], $context['employee'], $type, [
        'start_date' => '2026-08-10', 'end_date' => '2026-08-10', 'reason' => 'Approved personal unpaid absence request.',
    ]);
    app(LeaveManager::class)->review($context['manager_user'], $request, [
        'decision' => 'approve', 'review_notes' => 'Manager approves the requested date.', 'idempotency_key' => 'up-manager',
    ]);
    app(LeaveManager::class)->review($context['hr'], $request->refresh(), [
        'decision' => 'approve', 'review_notes' => 'HR validates unpaid leave classification.', 'idempotency_key' => 'up-hr',
    ]);
    expect($request->refresh()->requestStatus())->toBe(LeaveRequestStatus::PendingPayroll)
        ->and(LeaveLedgerEntry::query()->count())->toBe(0);
    app(LeaveManager::class)->review($context['payroll'], $request->refresh(), [
        'decision' => 'approve', 'review_notes' => 'Payroll confirms downstream unpaid input only.', 'idempotency_key' => 'up-payroll',
    ]);
    expect($request->refresh()->requestStatus())->toBe(LeaveRequestStatus::Approved)
        ->and(LeaveLedgerEntry::query()->count())->toBe(0);
});

test('eligibility evidence balance and overlap validations fail without partial writes', function () {
    $context = phaseEightContext();
    EmploymentHistory::query()->where('employee_id', $context['employee']->getKey())->update(['join_date' => '2026-01-01']);
    $type = phaseEightLeaveType($context, ['code' => 'SICK', 'name' => 'Sick leave', 'category' => 'sick', 'evidence_required_from_days' => 2]);
    phaseEightEntitlement($context, $type, '1.00', 'SICK-2026');
    $manager = app(LeaveManager::class);

    expect(fn () => $manager->submit($context['employee_user'], $context['employee'], $type, [
        'start_date' => '2026-08-10', 'end_date' => '2026-08-11', 'reason' => 'Medical recovery requires two working days.',
    ]))->toThrow(ValidationException::class)
        ->and(LeaveRequest::query()->count())->toBe(0);

    EmploymentHistory::query()->where('employee_id', $context['employee']->getKey())->update(['join_date' => '2025-01-01']);
    expect(fn () => $manager->submit($context['employee_user'], $context['employee'], $type, [
        'start_date' => '2026-08-10', 'end_date' => '2026-08-11', 'reason' => 'Medical recovery requires two working days.',
    ]))->toThrow(ValidationException::class)
        ->and(LeaveRequest::query()->count())->toBe(0);

    $evidence = UploadedFile::fake()->create('doctor-note.pdf', 100, 'application/pdf');
    $response = $this->actingAs($context['employee_user'])->post(route('leave.requests.store'), [
        'leave_type_public_id' => $type->public_id, 'start_date' => '2026-08-10', 'end_date' => '2026-08-11',
        'reason' => 'Medical recovery requires two working days.', 'evidence' => $evidence,
    ]);
    $response->assertSessionHasErrors('leave_type_public_id');
    expect(LeaveRequest::query()->count())->toBe(0);
});

test('active delegation resolves the current approver and cross entity review fails closed', function () {
    $context = phaseEightContext('ONE');
    $other = phaseEightContext('TWO');
    $delegateEmployee = Employee::query()->create([
        'legal_entity_id' => $context['entity']->getKey(), 'employee_number' => 'ONE-DEL', 'full_name' => 'Delegate ONE',
        'status' => 'active', 'created_by' => $context['admin']->getKey(), 'updated_by' => $context['admin']->getKey(),
    ]);
    $delegate = phaseEightUser('Supervisor', $delegateEmployee, 'one.phase8.delegate@example.test');
    $delegation = app(ApprovalEngine::class)->createDelegation(
        $context['hr'], $context['entity'], $context['manager_user'], $delegate,
        ['effective_from' => '2026-08-01', 'effective_to' => '2026-08-31', 'reason' => 'Manager leave coverage with approved temporary delegation.'],
    );
    $type = phaseEightLeaveType($context);
    phaseEightEntitlement($context, $type);
    $request = app(LeaveManager::class)->submit($context['employee_user'], $context['employee'], $type, [
        'start_date' => '2026-08-10', 'end_date' => '2026-08-10', 'reason' => 'One-day planned leave during delegated approval period.',
    ]);
    $firstStep = $request->approvalInstance?->steps->first();

    expect($firstStep?->assigned_approver_user_id)->toBe($delegate->getKey())
        ->and($firstStep?->delegated_from_user_id)->toBe($context['manager_user']->getKey())
        ->and($delegation)->toBeInstanceOf(ApprovalDelegation::class);
    expect(fn () => app(LeaveManager::class)->review($context['manager_user'], $request, [
        'decision' => 'approve', 'review_notes' => 'Original manager must not bypass active delegation.', 'idempotency_key' => 'wrong-manager',
    ]))->toThrow(HttpException::class);
    $this->actingAs($other['hr'])->put(route('leave.review.update', $request), [
        'decision' => 'approve', 'review_notes' => 'Cross company review must fail closed.',
    ])->assertNotFound();
});

test('pending request cancellation preserves balance and entitlement expiry posts a reversing ledger entry', function () {
    $context = phaseEightContext();
    $type = phaseEightLeaveType($context);
    $entitlement = phaseEightEntitlement($context, $type, '3.00');
    $request = app(LeaveManager::class)->submit($context['employee_user'], $context['employee'], $type, [
        'start_date' => '2026-08-10', 'end_date' => '2026-08-10', 'reason' => 'Request will be cancelled before any approval.',
    ]);
    app(LeaveManager::class)->cancel($context['employee_user'], $request);
    expect($request->refresh()->requestStatus())->toBe(LeaveRequestStatus::Cancelled)
        ->and($entitlement->refresh()->balance())->toBe('3.00');

    $entitlement->update(['valid_to' => '2026-08-02']);
    $processed = app(LeaveManager::class)->expireDue('2026-08-03');
    expect($processed)->toBe(1)
        ->and($entitlement->refresh()->status)->toBe('expired')
        ->and($entitlement->balance())->toBe('0.00')
        ->and(LeaveLedgerEntry::query()->where('entry_type', 'expiry')->value('quantity'))->toBe('-3.00');
});

test('leave interfaces are bilingual scoped and read only users cannot mutate configuration', function () {
    $context = phaseEightContext();
    $type = phaseEightLeaveType($context);
    phaseEightEntitlement($context, $type);
    $this->actingAs($context['employee_user'])->withSession(['locale' => 'id'])->get(route('leave.index'))
        ->assertOk()->assertSee('Ajukan cuti atau izin')->assertSee('Saldo tersedia');
    $this->actingAs($context['employee_user'])->withSession(['locale' => 'en'])->get(route('leave.index'))
        ->assertOk()->assertSee('Submit leave or permit')->assertSee('Available balance');

    $auditor = phaseEightUser('Auditor', null, 'phase8.auditor@example.test');
    phaseEightScope($auditor, $context['entity'], $context['admin'], 'view');
    $this->actingAs($auditor)->get(route('leave.admin.index'))
        ->assertOk()->assertSee(__('leave.read_only'))->assertDontSee(__('leave.create_type'));
    $this->actingAs($auditor)->post(route('leave.admin.types.store'), [])->assertForbidden();
});

test('authorized leave CSV export is scoped and audited', function () {
    $context = phaseEightContext();
    $other = phaseEightContext('OTH');
    $type = phaseEightLeaveType($context);
    $otherType = phaseEightLeaveType($other);
    phaseEightEntitlement($context, $type);
    phaseEightEntitlement($other, $otherType);
    app(LeaveManager::class)->submit($context['employee_user'], $context['employee'], $type, [
        'start_date' => '2026-08-10', 'end_date' => '2026-08-10', 'reason' => 'Scoped export record for one legal entity.',
    ]);
    app(LeaveManager::class)->submit($other['employee_user'], $other['employee'], $otherType, [
        'start_date' => '2026-08-10', 'end_date' => '2026-08-10', 'reason' => 'Other entity record must not be exported.',
    ]);
    $response = $this->actingAs($context['hr'])->get(route('leave.admin.report.export'));
    $response->assertOk();
    $content = $response->streamedContent();
    expect($content)->toContain($context['employee']->employee_number)
        ->not->toContain($other['employee']->employee_number);
    expect(DB::table('activity_log')->where('event', 'leave_report_exported')->exists())->toBeTrue();
});

test('phase eight documentation has no open placeholders', function () {
    foreach (['scope-and-security.md', 'policy-entitlement-ledger.md', 'request-and-approval-workflow.md', 'expiry-notifications-and-reports.md', 'operations-runbook.md', 'phase-8-exit-review.md'] as $document) {
        $contents = file_get_contents(base_path('docs/08-leave/'.$document));
        expect($contents)->toBeString()->not->toContain('[TODO]', '[TBD]')->and(strlen((string) $contents))->toBeGreaterThan(500);
    }
});
