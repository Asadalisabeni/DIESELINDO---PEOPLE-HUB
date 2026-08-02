<?php

use App\Models\Branch;
use App\Models\Contract;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmploymentHistory;
use App\Models\LegalEntity;
use App\Models\Position;
use App\Models\User;
use App\Models\UserLegalEntityAccess;
use App\Models\WorkLocation;
use App\Services\Employee\EmployeeManager;
use App\Services\Organization\OrganizationStructureManager;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function phaseFiveUser(string $role = 'Super Admin'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/** @return array{entity: LegalEntity, branch: Branch, department: Department, position: Position, work_location: WorkLocation, cost_center: CostCenter} */
function phaseFiveOrganization(User $actor, string $code): array
{
    $manager = app(OrganizationStructureManager::class);
    $entity = $manager->createLegalEntity($actor, [
        'code' => $code,
        'legal_name' => 'PT '.$code,
        'display_name' => $code.' Company',
        'country_code' => 'ID',
        'timezone' => 'Asia/Jakarta',
        'currency' => 'IDR',
        'status' => 'active',
    ]);
    $branch = $manager->createUnit($actor, $entity, 'branches', [
        'code' => $code.'-BR', 'name' => $code.' Branch', 'status' => 'active', 'timezone' => 'Asia/Jakarta',
    ]);
    $division = $manager->createUnit($actor, $entity, 'divisions', [
        'code' => $code.'-DIV', 'name' => $code.' Division', 'status' => 'active',
    ]);
    $department = $manager->createUnit($actor, $entity, 'departments', [
        'code' => $code.'-DEP', 'name' => $code.' Department', 'status' => 'active',
        'branch_public_id' => $branch->public_id, 'division_public_id' => $division->public_id,
    ]);
    $position = $manager->createUnit($actor, $entity, 'positions', [
        'code' => $code.'-POS', 'name' => $code.' Position', 'status' => 'active',
        'department_public_id' => $department->public_id,
    ]);
    $workLocation = $manager->createUnit($actor, $entity, 'work-locations', [
        'code' => $code.'-LOC', 'name' => $code.' Location', 'status' => 'active',
        'branch_public_id' => $branch->public_id, 'timezone' => 'Asia/Jakarta',
    ]);
    $costCenter = $manager->createUnit($actor, $entity, 'cost-centers', [
        'code' => $code.'-CC', 'name' => $code.' Cost Center', 'status' => 'active',
    ]);

    return compact('entity', 'branch', 'department', 'position', 'workLocation', 'costCenter') + [
        'work_location' => $workLocation,
        'cost_center' => $costCenter,
    ];
}

/** @param array<string, mixed> $organization
 * @return array<string, mixed>
 */
function phaseFiveEmployeePayload(array $organization, string $number = 'EMP-001'): array
{
    return [
        'legal_entity_public_id' => $organization['entity']->public_id,
        'employee_number' => $number,
        'full_name' => 'Ayu Pratama',
        'nik' => '3174000000000001',
        'birth_place' => 'Jakarta',
        'birth_date' => '1990-01-15',
        'gender' => 'female',
        'marital_status' => 'married',
        'personal_email' => 'ayu.personal@example.test',
        'company_email' => 'ayu@example.test',
        'phone' => '081234567890',
        'address' => 'Private home address',
        'emergency_name' => 'Budi Pratama',
        'emergency_relationship' => 'Spouse',
        'emergency_phone' => '081298765432',
        'branch_public_id' => $organization['branch']->public_id,
        'department_public_id' => $organization['department']->public_id,
        'position_public_id' => $organization['position']->public_id,
        'work_location_public_id' => $organization['work_location']->public_id,
        'cost_center_public_id' => $organization['cost_center']->public_id,
        'employment_status' => 'permanent',
        'join_date' => '2026-01-01',
        'effective_from' => '2026-01-01',
        'change_reason' => 'Initial onboarding',
        'contract_type' => 'permanent',
        'contract_number' => $number.'-CTR-001',
        'contract_start_date' => '2026-01-01',
        'bank_code' => 'BCA',
        'bank_name' => 'Bank Central Asia',
        'bank_account_number' => '444455556666',
        'bank_account_holder' => 'Ayu Pratama',
        'tax_identifier' => '09.888.777.6-123.000',
        'ptkp_code' => 'K/0',
        'bpjs_health_number' => '0001234567890',
        'bpjs_employment_number' => '98765432100',
        'jkk_risk_category' => 'low',
    ];
}

function phaseFiveCreateEmployee(User $actor, array $organization, string $number = 'EMP-001'): Employee
{
    return app(EmployeeManager::class)->create(
        $actor,
        $organization['entity'],
        phaseFiveEmployeePayload($organization, $number),
    );
}

test('phase five schema exposes public identifiers tenant indexes and the employee account link', function () {
    foreach (['legal_entities', 'branches', 'divisions', 'departments', 'positions', 'employees', 'employment_histories', 'contracts', 'employee_documents'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    expect(Schema::hasColumn('users', 'employee_id'))->toBeTrue()
        ->and(Schema::hasColumn('employees', 'public_id'))->toBeTrue()
        ->and(Schema::hasColumn('employees', 'legal_entity_id'))->toBeTrue();
});

test('a super admin has capabilities but no implicit employee row scope', function () {
    $admin = phaseFiveUser();

    $this->actingAs($admin)->get('/organization')->assertOk()->assertSee('Struktur organisasi');
    $this->actingAs($admin)->get('/employees')->assertForbidden();
});

test('creating the first legal entity atomically grants its creator an effective scope', function () {
    $admin = phaseFiveUser();

    $this->actingAs($admin)->post('/organization/legal-entities', [
        'code' => 'DUN',
        'legal_name' => 'PT Dieselindo Utama Nusa',
        'display_name' => 'Dieselindo Utama Nusa',
        'country_code' => 'ID',
        'timezone' => 'Asia/Jakarta',
        'currency' => 'IDR',
        'status' => 'active',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $entity = LegalEntity::query()->sole();
    expect($entity->public_id)->toHaveLength(26)
        ->and(UserLegalEntityAccess::query()->where('user_id', $admin->getKey())->where('legal_entity_id', $entity->getKey())->count())->toBe(1);

    $this->actingAs($admin)->get('/organization')->assertOk()->assertSee('Dieselindo Utama Nusa');
});

test('a company administrator cannot create a legal entity outside delegated scope', function () {
    $superAdmin = phaseFiveUser();
    $organization = phaseFiveOrganization($superAdmin, 'DUN');
    $companyAdmin = phaseFiveUser('Company HR Admin');
    UserLegalEntityAccess::query()->create([
        'user_id' => $companyAdmin->getKey(), 'legal_entity_id' => $organization['entity']->getKey(), 'access_level' => 'manage',
        'effective_from' => '2026-01-01', 'granted_by' => $superAdmin->getKey(), 'reason' => 'Delegated company administration.',
    ]);

    $this->actingAs($companyAdmin)->post(route('organization.legal-entities.store'), [
        'code' => 'UNSCOPED',
        'legal_name' => 'PT Unscoped Entity',
        'display_name' => 'Unscoped Entity',
        'country_code' => 'ID',
        'timezone' => 'Asia/Jakarta',
        'currency' => 'IDR',
        'status' => 'active',
    ])->assertForbidden();

    expect(LegalEntity::query()->where('code', 'UNSCOPED')->exists())->toBeFalse();
});

test('organization hierarchy rejects a cross entity branch reference without partial writes', function () {
    $admin = phaseFiveUser();
    $first = phaseFiveOrganization($admin, 'ONE');
    $second = phaseFiveOrganization($admin, 'TWO');
    $before = Department::query()->count();

    $this->actingAs($admin)->post(route('organization.units.store', [$first['entity']->public_id, 'departments']), [
        'code' => 'ONE-X',
        'name' => 'Invalid cross entity department',
        'status' => 'active',
        'branch_public_id' => $second['branch']->public_id,
    ])->assertRedirect()->assertSessionHasErrors('organization');

    expect(Department::query()->count())->toBe($before);
});

test('employee creation encrypts restricted fields and writes the complete initial history in one transaction', function () {
    $admin = phaseFiveUser();
    $organization = phaseFiveOrganization($admin, 'DUN');

    $this->actingAs($admin)->post('/employees', phaseFiveEmployeePayload($organization))
        ->assertRedirect()->assertSessionHasNoErrors();

    $employee = Employee::query()->sole();
    $rawEmployee = DB::table('employees')->where('id', $employee->getKey())->first();
    $rawContact = DB::table('employee_contacts')->where('employee_id', $employee->getKey())->where('type', 'phone')->first();
    $rawBank = DB::table('employee_bank_accounts')->where('employee_id', $employee->getKey())->first();

    expect($employee->public_id)->toHaveLength(26)
        ->and((string) $rawEmployee->nik)->not->toContain('3174000000000001')
        ->and((string) $rawContact->value)->not->toContain('081234567890')
        ->and((string) $rawBank->account_number)->not->toContain('444455556666')
        ->and(EmploymentHistory::query()->where('employee_id', $employee->getKey())->count())->toBe(1)
        ->and(Contract::query()->where('employee_id', $employee->getKey())->count())->toBe(1);
});

test('an employee outside the actor legal entity scope is indistinguishable from a missing resource', function () {
    $admin = phaseFiveUser();
    $one = phaseFiveOrganization($admin, 'ONE');
    $two = phaseFiveOrganization($admin, 'TWO');
    $outsideEmployee = phaseFiveCreateEmployee($admin, $two, 'TWO-001');
    $groupHr = phaseFiveUser('Group HR Admin');
    UserLegalEntityAccess::query()->create([
        'user_id' => $groupHr->getKey(), 'legal_entity_id' => $one['entity']->getKey(), 'access_level' => 'manage',
        'effective_from' => '2026-01-01', 'granted_by' => $admin->getKey(), 'reason' => 'Entity ONE only.',
    ]);

    $this->actingAs($groupHr)->get(route('employees.show', $outsideEmployee))->assertNotFound();
});

test('view scope permits reading but never employee mutation even when the role has update capabilities', function () {
    $admin = phaseFiveUser();
    $organization = phaseFiveOrganization($admin, 'DUN');
    $employee = phaseFiveCreateEmployee($admin, $organization);
    $companyHr = phaseFiveUser('Company HR Admin');
    UserLegalEntityAccess::query()->create([
        'user_id' => $companyHr->getKey(), 'legal_entity_id' => $organization['entity']->getKey(), 'access_level' => 'view',
        'effective_from' => '2026-01-01', 'granted_by' => $admin->getKey(), 'reason' => 'Read-only review.',
    ]);

    $this->actingAs($companyHr)->get(route('employees.show', $employee))->assertOk();
    $this->actingAs($companyHr)->put(route('employees.update', $employee), [
        'full_name' => 'Unauthorized Change',
        'status' => 'active',
        'effective_from' => now()->toDateString(),
    ])->assertForbidden();
    $this->actingAs($companyHr)->post(route('employees.documents.store', $employee), [
        'type' => 'contract',
        'document' => UploadedFile::fake()->create('attempt.pdf', 1, 'application/pdf'),
        'classification' => 'restricted',
    ])->assertForbidden();

    expect($employee->refresh()->full_name)->toBe('Ayu Pratama');
});

test('an effective assignment closes the previous interval and transfers the current entity cache', function () {
    Storage::fake('local');
    $admin = phaseFiveUser();
    $one = phaseFiveOrganization($admin, 'ONE');
    $two = phaseFiveOrganization($admin, 'TWO');
    $employee = phaseFiveCreateEmployee($admin, $one);

    $this->actingAs($admin)->post(route('employees.documents.store', $employee), [
        'type' => 'contract',
        'document' => UploadedFile::fake()->create('source-entity-contract.pdf', 12, 'application/pdf'),
        'classification' => 'restricted',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $payload = [
        'legal_entity_public_id' => $two['entity']->public_id,
        'employee_number' => 'TWO-001',
        'branch_public_id' => $two['branch']->public_id,
        'department_public_id' => $two['department']->public_id,
        'position_public_id' => $two['position']->public_id,
        'work_location_public_id' => $two['work_location']->public_id,
        'cost_center_public_id' => $two['cost_center']->public_id,
        'employment_status' => 'permanent',
        'effective_from' => now()->toDateString(),
        'change_reason' => 'Inter-company transfer approved.',
    ];

    $this->actingAs($admin)->post(route('employees.assignments.store', $employee), $payload)
        ->assertRedirect()->assertSessionHasNoErrors();

    $histories = EmploymentHistory::query()->where('employee_id', $employee->getKey())->orderBy('effective_from')->get();
    expect($histories)->toHaveCount(2)
        ->and($histories->first()->effective_to->toDateString())->toBe(now()->toDateString())
        ->and($employee->refresh()->legal_entity_id)->toBe($two['entity']->getKey())
        ->and($employee->employee_number)->toBe('TWO-001');

    $targetAuditor = phaseFiveUser('Auditor');
    UserLegalEntityAccess::query()->create([
        'user_id' => $targetAuditor->getKey(), 'legal_entity_id' => $two['entity']->getKey(), 'access_level' => 'view',
        'effective_from' => '2026-01-01', 'granted_by' => $admin->getKey(), 'reason' => 'Target entity audit.',
    ]);

    $this->actingAs($targetAuditor)->get(route('employees.show', $employee))
        ->assertOk()
        ->assertSee('TWO Company')
        ->assertDontSee('ONE Company')
        ->assertDontSee('Private home address')
        ->assertDontSee('source-entity-contract.pdf')
        ->assertDontSee('EMP-001-CTR-001')
        ->assertDontSee('6666');

    $targetAdmin = phaseFiveUser('Company HR Admin');
    UserLegalEntityAccess::query()->create([
        'user_id' => $targetAdmin->getKey(), 'legal_entity_id' => $two['entity']->getKey(), 'access_level' => 'manage',
        'effective_from' => '2026-01-01', 'granted_by' => $admin->getKey(), 'reason' => 'Target entity administration.',
    ]);
    $this->actingAs($targetAdmin)->put(route('employees.update', $employee), [
        'full_name' => $employee->full_name,
        'nik' => $employee->nik,
        'birth_place' => $employee->birth_place,
        'birth_date' => $employee->birth_date?->toDateString(),
        'gender' => $employee->gender,
        'marital_status' => $employee->marital_status,
        'personal_email' => $employee->personal_email,
        'company_email' => $employee->company_email,
        'phone' => '081200000002',
        'address' => 'Target entity address',
        'status' => 'active',
        'effective_from' => now()->toDateString(),
    ])->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($targetAdmin)->post(route('employees.contracts.store', $employee), [
        'contract_type' => 'permanent',
        'contract_number' => 'TWO-001-CTR-001',
        'start_date' => now()->toDateString(),
        'change_reason' => 'Target entity contract.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(DB::table('employee_contacts')->where('employee_id', $employee->getKey())->where('legal_entity_id', $one['entity']->getKey())->whereNull('effective_to')->exists())->toBeTrue()
        ->and(DB::table('employee_contacts')->where('employee_id', $employee->getKey())->where('legal_entity_id', $two['entity']->getKey())->exists())->toBeTrue()
        ->and(Contract::query()->where('employee_id', $employee->getKey())->where('legal_entity_id', $one['entity']->getKey())->where('status', 'active')->exists())->toBeTrue()
        ->and(Contract::query()->where('employee_id', $employee->getKey())->where('legal_entity_id', $two['entity']->getKey())->where('status', 'active')->exists())->toBeTrue();
});

test('an employee cannot become their own direct manager', function () {
    $admin = phaseFiveUser();
    $organization = phaseFiveOrganization($admin, 'DUN');
    $employee = phaseFiveCreateEmployee($admin, $organization);

    $payload = [
        'legal_entity_public_id' => $organization['entity']->public_id,
        'employee_number' => $employee->employee_number,
        'branch_public_id' => $organization['branch']->public_id,
        'department_public_id' => $organization['department']->public_id,
        'position_public_id' => $organization['position']->public_id,
        'manager_public_id' => $employee->public_id,
        'employment_status' => 'permanent',
        'effective_from' => '2026-02-01',
        'change_reason' => 'Invalid self manager.',
    ];

    $this->actingAs($admin)->post(route('employees.assignments.store', $employee), $payload)
        ->assertRedirect()->assertSessionHasErrors('manager_public_id');

    expect(EmploymentHistory::query()->where('employee_id', $employee->getKey())->count())->toBe(1);
});

test('a retroactive transfer cannot mutate history owned by an unmanaged entity', function () {
    $admin = phaseFiveUser();
    $one = phaseFiveOrganization($admin, 'ONE');
    $two = phaseFiveOrganization($admin, 'TWO');
    $three = phaseFiveOrganization($admin, 'THREE');
    $employee = phaseFiveCreateEmployee($admin, $one);

    $toThree = [
        'legal_entity_public_id' => $three['entity']->public_id,
        'employee_number' => 'THREE-001',
        'branch_public_id' => $three['branch']->public_id,
        'department_public_id' => $three['department']->public_id,
        'position_public_id' => $three['position']->public_id,
        'employment_status' => 'permanent',
        'effective_from' => '2026-06-01',
        'change_reason' => 'First transfer.',
    ];
    $this->actingAs($admin)->post(route('employees.assignments.store', $employee), $toThree)
        ->assertRedirect()->assertSessionHasNoErrors();

    $groupHr = phaseFiveUser('Group HR Admin');
    foreach ([$two['entity'], $three['entity']] as $entity) {
        UserLegalEntityAccess::query()->create([
            'user_id' => $groupHr->getKey(), 'legal_entity_id' => $entity->getKey(), 'access_level' => 'manage',
            'effective_from' => '2026-01-01', 'granted_by' => $admin->getKey(), 'reason' => 'Limited transfer scope.',
        ]);
    }

    $retroactive = [
        'legal_entity_public_id' => $two['entity']->public_id,
        'employee_number' => 'TWO-001',
        'branch_public_id' => $two['branch']->public_id,
        'department_public_id' => $two['department']->public_id,
        'position_public_id' => $two['position']->public_id,
        'employment_status' => 'permanent',
        'effective_from' => '2026-03-01',
        'change_reason' => 'Unauthorized historical rewrite.',
    ];
    $this->actingAs($groupHr)->post(route('employees.assignments.store', $employee), $retroactive)
        ->assertRedirect()->assertSessionHasErrors('legal_entity');

    expect(EmploymentHistory::query()->where('employee_id', $employee->getKey())->count())->toBe(2)
        ->and(EmploymentHistory::query()->where('employee_id', $employee->getKey())->oldest('effective_from')->firstOrFail()->effective_to?->toDateString())->toBe('2026-06-01');
});

test('contract renewal preserves the prior contract as superseded history', function () {
    $admin = phaseFiveUser();
    $organization = phaseFiveOrganization($admin, 'DUN');
    $employee = phaseFiveCreateEmployee($admin, $organization);

    $this->actingAs($admin)->post(route('employees.contracts.store', $employee), [
        'contract_type' => 'fixed_term',
        'contract_number' => 'EMP-001-CTR-002',
        'start_date' => '2027-01-01',
        'end_date' => '2027-12-31',
        'change_reason' => 'Approved renewal.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(Contract::query()->where('employee_id', $employee->getKey())->count())->toBe(2)
        ->and(Contract::query()->where('employee_id', $employee->getKey())->where('status', 'superseded')->count())->toBe(1)
        ->and(Contract::query()->where('employee_id', $employee->getKey())->where('status', 'active')->count())->toBe(1);
});

test('private employee documents use generated paths and require scoped download permission', function () {
    Storage::fake('local');
    $admin = phaseFiveUser();
    $organization = phaseFiveOrganization($admin, 'DUN');
    $employee = phaseFiveCreateEmployee($admin, $organization);
    $file = UploadedFile::fake()->create('signed-contract.pdf', 12, 'application/pdf');

    $this->actingAs($admin)->post(route('employees.documents.store', $employee), [
        'type' => 'contract',
        'document' => $file,
        'issued_date' => '2026-01-01',
        'classification' => 'restricted',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $document = EmployeeDocument::query()->sole();
    Storage::disk('local')->assertExists($document->storage_path);
    expect($document->storage_path)->toStartWith('employee-documents/'.$employee->public_id.'/')
        ->and($document->storage_path)->not->toContain('signed-contract');

    $employeeUser = phaseFiveUser('Employee');
    UserLegalEntityAccess::query()->create([
        'user_id' => $employeeUser->getKey(), 'legal_entity_id' => $organization['entity']->getKey(), 'access_level' => 'view',
        'effective_from' => '2026-01-01', 'granted_by' => $admin->getKey(), 'reason' => 'Employee scope.',
    ]);

    $this->actingAs($employeeUser)->get(route('employee-documents.download', $document))->assertForbidden();
    $supervisor = phaseFiveUser('Supervisor');
    UserLegalEntityAccess::query()->create([
        'user_id' => $supervisor->getKey(), 'legal_entity_id' => $organization['entity']->getKey(), 'access_level' => 'view',
        'effective_from' => '2026-01-01', 'granted_by' => $admin->getKey(), 'reason' => 'Supervisor scope.',
    ]);
    $this->actingAs($supervisor)->get(route('employees.show', $employee))
        ->assertOk()
        ->assertDontSee('signed-contract.pdf')
        ->assertDontSee('0001');

    $otherOrganization = phaseFiveOrganization($admin, 'OTHER');
    $auditor = phaseFiveUser('Auditor');
    UserLegalEntityAccess::query()->create([
        'user_id' => $auditor->getKey(), 'legal_entity_id' => $otherOrganization['entity']->getKey(), 'access_level' => 'view',
        'effective_from' => '2026-01-01', 'granted_by' => $admin->getKey(), 'reason' => 'Different entity scope.',
    ]);
    $this->actingAs($auditor)->get(route('employee-documents.download', $document))->assertNotFound();

    $response = $this->actingAs($admin)->get(route('employee-documents.download', $document));
    $response->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Cache-Control');
    expect((string) $response->headers->get('Cache-Control'))
        ->toContain('private')
        ->toContain('no-store')
        ->toContain('max-age=0');
});

test('the employee detail masks national bank tax and bpjs identifiers even for an authorized administrator', function () {
    $admin = phaseFiveUser();
    $organization = phaseFiveOrganization($admin, 'DUN');
    $employee = phaseFiveCreateEmployee($admin, $organization);

    $this->actingAs($admin)->get(route('employees.show', $employee))
        ->assertOk()
        ->assertDontSee('3174000000000001')
        ->assertDontSee('444455556666')
        ->assertDontSee('09.888.777.6-123.000')
        ->assertSee('0001')
        ->assertSee('6666');
});

test('legal entity access periods reject overlap and retain an auditable history', function () {
    $admin = phaseFiveUser();
    $organization = phaseFiveOrganization($admin, 'DUN');
    $target = phaseFiveUser('Company HR Admin');
    $payload = [
        'legal_entity_public_id' => $organization['entity']->public_id,
        'access_level' => 'manage',
        'effective_from' => '2026-01-01',
        'reason' => 'Initial company HR assignment.',
    ];

    $this->actingAs($admin)->post(route('iam.entity-access.store', $target), $payload)
        ->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(route('iam.entity-access.store', $target), array_replace($payload, ['effective_from' => '2026-06-01']))
        ->assertRedirect()->assertSessionHasErrors('effective_from');

    $access = UserLegalEntityAccess::query()->where('user_id', $target->getKey())->sole();
    expect(UserLegalEntityAccess::query()->where('user_id', $target->getKey())->count())->toBe(1);

    $this->actingAs($admin)->put(route('iam.entity-access.end', $access), [
        'effective_to' => now()->toDateString(),
        'reason' => 'Assignment completed.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($access->refresh()->effective_to?->toDateString())->toBe(now()->toDateString());
    $this->actingAs($target)->get(route('employees.index'))->assertForbidden();
});

test('business audit events fail closed outside the actor legal entity scope', function () {
    $admin = phaseFiveUser();
    $one = phaseFiveOrganization($admin, 'ONE');
    $two = phaseFiveOrganization($admin, 'TWO');
    $auditor = phaseFiveUser('Auditor');
    UserLegalEntityAccess::query()->create([
        'user_id' => $auditor->getKey(), 'legal_entity_id' => $one['entity']->getKey(), 'access_level' => 'view',
        'effective_from' => '2026-01-01', 'granted_by' => $admin->getKey(), 'reason' => 'Entity audit scope.',
    ]);

    activity('employee')->causedBy($admin)->withProperties([
        'legal_entity_public_id' => $one['entity']->public_id,
    ])->log('Scoped ONE business event.');
    activity('employee')->causedBy($admin)->withProperties([
        'legal_entity_public_id' => $two['entity']->public_id,
    ])->log('Scoped TWO business event.');
    activity('employee')->causedBy($admin)->log('Unscoped business event.');

    $this->actingAs($auditor)->get(route('audit.index'))
        ->assertOk()
        ->assertSee('Scoped ONE business event.')
        ->assertDontSee('Scoped TWO business event.')
        ->assertDontSee('Unscoped business event.');
});

test('organization and employee interfaces render in English from translation keys', function () {
    $admin = phaseFiveUser();
    phaseFiveOrganization($admin, 'DUN');

    $this->actingAs($admin)->withSession(['locale' => 'en'])->get('/organization')
        ->assertOk()->assertSee('Organization structure');
    $this->actingAs($admin)->withSession(['locale' => 'en'])->get('/employees')
        ->assertOk()->assertSee('Employees');
});

test('the phase five review package is complete and contains no open placeholders', function () {
    $documents = [
        'organization-and-scope.md',
        'core-hr-data-model.md',
        'private-documents.md',
        'operations-runbook.md',
        'phase-5-exit-review.md',
    ];

    foreach ($documents as $document) {
        $contents = file_get_contents(base_path('docs/05-organization-core-hr/'.$document));

        expect($contents)->toBeString()
            ->not->toContain('[TODO]', '[TBD]')
            ->and(strlen((string) $contents))->toBeGreaterThan(500);
    }
});
