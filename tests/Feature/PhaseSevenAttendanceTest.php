<?php

use App\Enums\AttendanceCorrectionStatus;
use App\Enums\AttendanceEventStatus;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSource;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentHistory;
use App\Models\LegalEntity;
use App\Models\Position;
use App\Models\User;
use App\Models\UserLegalEntityAccess;
use App\Models\WorkSchedule;
use App\Services\Attendance\AttendanceManager;
use App\Services\Attendance\X100cCsvImporter;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-08-03 03:00:00');
    $this->seed(RolePermissionSeeder::class);
});

afterEach(fn () => Carbon::setTestNow());

function phaseSevenUser(string $role, ?Employee $employee = null, string $email = 'phase7@example.test'): User
{
    $user = User::factory()->create(['email' => $email, 'password' => Hash::make('ValidPassword!2026')]);
    $user->assignRole($role);
    if ($employee) {
        $user->forceFill(['employee_id' => $employee->getKey()])->save();
    }

    return $user;
}

/** @return array{admin: User, entity: LegalEntity, branch: Branch, department: Department, position: Position, manager: Employee, employee: Employee, manager_user: User, employee_user: User} */
function phaseSevenContext(string $code = 'DUN'): array
{
    $admin = phaseSevenUser('Super Admin', null, strtolower($code).'.admin@example.test');
    $entity = LegalEntity::query()->create([
        'code' => $code, 'legal_name' => 'PT '.$code, 'display_name' => $code.' Company',
        'country_code' => 'ID', 'timezone' => 'Asia/Jakarta', 'currency' => 'IDR', 'status' => 'active',
        'created_by' => $admin->getKey(), 'updated_by' => $admin->getKey(),
    ]);
    UserLegalEntityAccess::query()->create([
        'user_id' => $admin->getKey(), 'legal_entity_id' => $entity->getKey(), 'access_level' => 'manage',
        'effective_from' => '2026-01-01', 'granted_by' => $admin->getKey(), 'reason' => 'Phase 7 test scope.',
    ]);
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
            'join_date' => '2026-01-01', 'effective_from' => '2026-01-01', 'change_reason' => 'Phase 7 test assignment.',
            'source' => 'manual', 'created_by' => $admin->getKey(),
        ]);
    }
    $managerUser = phaseSevenUser('Supervisor', $manager, strtolower($code).'.manager@example.test');
    $employeeUser = phaseSevenUser('Employee', $employee, strtolower($code).'.employee@example.test');

    return compact('admin', 'entity', 'branch', 'department', 'position', 'manager', 'employee', 'managerUser', 'employeeUser') + [
        'manager_user' => $managerUser, 'employee_user' => $employeeUser,
    ];
}

/** @return array<int, array{is_working_day: bool, start_time: ?string, end_time: ?string, break_minutes: int}> */
function phaseSevenDays(string $start = '08:00', string $end = '17:00'): array
{
    $days = [];
    foreach (range(1, 7) as $day) {
        $working = $day <= 5;
        $days[$day] = ['is_working_day' => $working, 'start_time' => $working ? $start : null, 'end_time' => $working ? $end : null, 'break_minutes' => $working ? 60 : 0];
    }

    return $days;
}

function phaseSevenSchedule(array $context, int $lateGrace = 15, ?string $departmentPublicId = null): WorkSchedule
{
    return app(AttendanceManager::class)->createSchedule($context['admin'], $context['entity'], [
        'code' => 'REG-'.$lateGrace.'-'.substr((string) $context['entity']->code, 0, 4),
        'name' => 'Configurable test schedule', 'timezone' => 'Asia/Jakarta',
        'late_grace_minutes' => $lateGrace, 'early_leave_grace_minutes' => 0,
        'effective_from' => '2026-01-01', 'effective_to' => null, 'days' => phaseSevenDays(),
        'department_public_id' => $departmentPublicId,
    ]);
}

function phaseSevenSource(array $context, string $code = 'WEB', string $type = 'web', string $adapter = 'web_gps_v1', array $overrides = []): AttendanceSource
{
    return app(AttendanceManager::class)->createSource($context['admin'], $context['entity'], array_merge([
        'code' => $code, 'name' => $code.' Source', 'type' => $type, 'adapter' => $adapter,
        'requires_gps' => false, 'requires_selfie' => false, 'max_gps_accuracy_meters' => 150,
        'max_offline_delay_minutes' => 720,
    ], $overrides));
}

test('phase seven schema and least privilege attendance capabilities are present', function () {
    foreach ([
        'work_schedules', 'work_schedule_days', 'employee_schedule_assignments', 'holidays', 'attendance_sources',
        'attendance_events', 'attendance_records', 'attendance_record_events', 'attendance_corrections', 'attendance_import_batches',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
    expect(phaseSevenUser('Employee')->can('attendance.clock'))->toBeTrue()
        ->and(phaseSevenUser('Employee', null, 'second@example.test')->can('attendance.manage'))->toBeFalse()
        ->and(phaseSevenUser('Company HR Admin', null, 'hr@example.test')->can('attendance.import'))->toBeTrue();
});

test('department fallback schedule and configurable grace normalize actual punches without payroll deduction', function () {
    $context = phaseSevenContext();
    $schedule = phaseSevenSchedule($context, 15, $context['department']->public_id);
    $source = phaseSevenSource($context);
    $manager = app(AttendanceManager::class);
    $checkIn = $manager->ingest($context['employee_user'], $context['employee'], $source, [
        'external_event_id' => 'WEB-IN-001', 'event_type' => 'check_in', 'occurred_at' => '2026-08-03T08:20:00+07:00',
        'latitude' => '-6.200000', 'longitude' => '106.816666', 'activity' => 'Verified field activity',
    ]);
    $manager->ingest($context['employee_user'], $context['employee'], $source, [
        'external_event_id' => 'WEB-OUT-001', 'event_type' => 'check_out', 'occurred_at' => '2026-08-03T17:00:00+07:00',
    ]);
    $record = AttendanceRecord::query()->where('is_current', true)->sole();
    $rawEvent = DB::table('attendance_events')->where('external_event_id', 'WEB-IN-001')->sole();

    expect($checkIn['duplicate'])->toBeFalse()
        ->and($record->work_schedule_id)->toBe($schedule->getKey())
        ->and($record->late_minutes)->toBe(5)
        ->and($record->check_in_at?->toIso8601String())->toContain('01:20:00')
        ->and($record->payroll_eligibility)->toBe('pending_review')
        ->and(AttendanceRecord::query()->count())->toBe(2)
        ->and((string) $rawEvent->latitude)->not->toContain('-6.200000')
        ->and((string) $rawEvent->longitude)->not->toContain('106.816666')
        ->and((string) $rawEvent->activity)->not->toContain('Verified field activity');
});

test('external IDs are idempotent and immutable raw events reject updates and deletes', function () {
    $context = phaseSevenContext();
    phaseSevenSchedule($context);
    $source = phaseSevenSource($context);
    $payload = ['external_event_id' => 'IDEMPOTENT-1', 'event_type' => 'check_in', 'occurred_at' => '2026-08-03T08:00:00+07:00'];
    $first = app(AttendanceManager::class)->ingest($context['employee_user'], $context['employee'], $source, $payload);
    $second = app(AttendanceManager::class)->ingest($context['employee_user'], $context['employee'], $source, $payload);

    expect($second['duplicate'])->toBeTrue()->and($second['event']->is($first['event']))->toBeTrue()
        ->and(AttendanceEvent::query()->count())->toBe(1);
    expect(fn () => $first['event']->update(['external_event_id' => 'MUTATED']))->toThrow(LogicException::class)
        ->and(fn () => $first['event']->delete())->toThrow(LogicException::class);
});

test('missing GPS and delayed offline sync are anomalous encrypted and payroll blocked', function () {
    $context = phaseSevenContext();
    phaseSevenSchedule($context);
    $source = phaseSevenSource($context, 'OFFLINE', 'offline_mobile', 'offline_mobile_v1', [
        'requires_gps' => true, 'max_gps_accuracy_meters' => 50, 'max_offline_delay_minutes' => 30,
    ]);
    $result = app(AttendanceManager::class)->ingest($context['employee_user'], $context['employee'], $source, [
        'external_event_id' => 'OFFLINE-001', 'event_type' => 'check_in',
        'occurred_at' => '2026-08-03T07:00:00+07:00', 'device_recorded_at' => '2026-08-03T07:00:00+07:00',
        'was_offline' => true, 'latitude' => null, 'longitude' => null,
    ]);

    expect($result['event']->status)->toBe(AttendanceEventStatus::Anomalous)
        ->and($result['event']->anomaly_codes)->toContain('gps_missing', 'offline_sync_delayed')
        ->and($result['event']->payroll_eligibility)->toBe('blocked')
        ->and($result['record']->payroll_eligibility)->toBe('blocked');
});

test('manager then scoped HR approval creates a new normalized version without changing raw punches', function () {
    $context = phaseSevenContext();
    phaseSevenSchedule($context);
    $source = phaseSevenSource($context);
    $manager = app(AttendanceManager::class);
    $manager->ingest($context['employee_user'], $context['employee'], $source, [
        'external_event_id' => 'CORR-IN', 'event_type' => 'check_in', 'occurred_at' => '2026-08-03T08:30:00+07:00',
    ]);
    $manager->ingest($context['employee_user'], $context['employee'], $source, [
        'external_event_id' => 'CORR-OUT', 'event_type' => 'check_out', 'occurred_at' => '2026-08-03T17:00:00+07:00',
    ]);
    $old = AttendanceRecord::query()->where('is_current', true)->sole();
    $correction = $manager->submitCorrection($context['employee_user'], $context['employee'], $old, [
        'type' => 'late_permission', 'reason' => 'Approved field assignment started before office check-in.',
        'proposed_check_in_at' => '2026-08-03T08:00:00+07:00', 'proposed_check_out_at' => '2026-08-03T17:00:00+07:00',
    ]);
    $manager->managerReview($context['manager_user'], $correction, ['decision' => 'approve', 'review_notes' => 'Direct report context and field duty verified.']);
    $manager->hrReview($context['admin'], $correction->refresh(), ['decision' => 'approve', 'review_notes' => 'HR verified evidence and schedule context.']);
    $new = AttendanceRecord::query()->where('is_current', true)->sole();

    expect($correction->refresh()->correctionStatus())->toBe(AttendanceCorrectionStatus::Approved)
        ->and($old->refresh()->is_current)->toBeFalse()
        ->and($new->normalization_version)->toBe($old->normalization_version + 1)
        ->and($new->supersedes_id)->toBe($old->getKey())
        ->and($new->late_minutes)->toBe(0)
        ->and(AttendanceEvent::query()->count())->toBe(2);
});

test('HR correction review fails closed outside managed legal entity scope', function () {
    $context = phaseSevenContext('ONE');
    $other = phaseSevenContext('TWO');
    $correction = AttendanceCorrection::query()->create([
        'legal_entity_id' => $context['entity']->getKey(), 'employee_id' => $context['employee']->getKey(),
        'requested_by' => $context['employee_user']->getKey(), 'type' => 'missing_check_in', 'status' => 'pending_hr',
        'reason' => 'A valid correction reason.', 'old_values' => [], 'proposed_values' => ['check_in_at' => '2026-08-03T08:00:00+07:00'],
        'snapshot_fingerprint' => str_repeat('a', 64), 'submitted_at' => now(),
    ]);

    $this->actingAs($other['admin'])->put(route('attendance.review.hr', $correction), [
        'decision' => 'approve', 'review_notes' => 'Attempted cross-company approval must not resolve.',
    ])->assertNotFound();
});

test('attendance configuration respects view and manage entity access levels', function () {
    $context = phaseSevenContext();
    phaseSevenSchedule($context);
    phaseSevenSource($context);
    $viewer = phaseSevenUser('Auditor', null, 'attendance.viewer@example.test');
    UserLegalEntityAccess::query()->create([
        'user_id' => $viewer->getKey(), 'legal_entity_id' => $context['entity']->getKey(), 'access_level' => 'view',
        'effective_from' => '2026-01-01', 'granted_by' => $context['admin']->getKey(), 'reason' => 'Read-only attendance audit.',
    ]);

    $this->actingAs($viewer)->get(route('attendance.admin.index'))
        ->assertOk()->assertSee(__('attendance.read_only'))->assertSee(__('attendance.configured_schedules'))
        ->assertDontSee(__('attendance.create_schedule'));
    $this->actingAs($viewer)->post(route('attendance.admin.holidays.store'), [
        'legal_entity_public_id' => $context['entity']->public_id, 'holiday_date' => '2026-08-17',
        'name' => 'National Holiday', 'type' => 'national',
    ])->assertForbidden();
});

test('correction times are interpreted in entity timezone and constrained to the work date', function () {
    $context = phaseSevenContext();
    phaseSevenSchedule($context);
    $source = phaseSevenSource($context);
    $manager = app(AttendanceManager::class);
    $manager->ingest($context['employee_user'], $context['employee'], $source, [
        'external_event_id' => 'LOCAL-TIME-IN', 'event_type' => 'check_in', 'occurred_at' => '2026-08-03 08:30:00',
    ]);
    $record = AttendanceRecord::query()->where('is_current', true)->sole();

    $correction = $manager->submitCorrection($context['employee_user'], $context['employee'], $record, [
        'type' => 'late_permission', 'reason' => 'Local schedule correction with verified permission.',
        'proposed_check_in_at' => '2026-08-03 08:00:00',
    ]);
    expect($correction->proposedValues()['check_in_at'])->toBe('2026-08-03T01:00:00+00:00');

    expect(fn () => $manager->submitCorrection($context['employee_user'], $context['employee'], $record, [
        'type' => 'late_permission', 'reason' => 'Invalid date should be rejected without a write.',
        'proposed_check_in_at' => '2026-08-04 08:00:00',
    ]))->toThrow(ValidationException::class);
});

test('X100C canonical CSV PoC reconciles imported and duplicate rows without real-time claims', function () {
    $context = phaseSevenContext();
    phaseSevenSchedule($context);
    $source = phaseSevenSource($context, 'X100C', 'fingerprint', 'x100c_csv_v1');
    $csv = "employee_number,event_type,occurred_at,external_event_id\n".
        "DUN-001,check_in,2026-08-03T08:00:00+07:00,X100C-1\n".
        "DUN-001,check_in,2026-08-03T08:00:00+07:00,X100C-1\n";
    $batch = app(X100cCsvImporter::class)->import($context['admin'], $source, UploadedFile::fake()->createWithContent('x100c.csv', $csv));

    expect($batch->status)->toBe('completed')
        ->and($batch->row_count)->toBe(2)
        ->and($batch->imported_count)->toBe(1)
        ->and($batch->duplicate_count)->toBe(1)
        ->and(AttendanceEvent::query()->count())->toBe(1);
});

test('attendance UI is bilingual and self scoped', function () {
    $context = phaseSevenContext();
    phaseSevenSource($context);

    $this->actingAs($context['employee_user'])->withSession(['locale' => 'id'])->get(route('attendance.index'))
        ->assertOk()->assertSee('Catat kehadiran')->assertSee('tidak ada potongan payroll otomatis');
    $this->actingAs($context['employee_user'])->withSession(['locale' => 'en'])->get(route('attendance.index'))
        ->assertOk()->assertSee('Record attendance')->assertSee('no automatic payroll deduction');
});

test('phase seven documentation has no open placeholders', function () {
    foreach (['scope-and-security.md', 'schedules-and-normalization.md', 'source-abstraction-and-x100c-spike.md', 'offline-gps-selfie.md', 'correction-workflow.md', 'operations-runbook.md', 'phase-7-exit-review.md'] as $document) {
        $contents = file_get_contents(base_path('docs/07-attendance/'.$document));
        expect($contents)->toBeString()->not->toContain('[TODO]', '[TBD]')->and(strlen((string) $contents))->toBeGreaterThan(500);
    }
});
