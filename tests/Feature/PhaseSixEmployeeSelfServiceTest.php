<?php

use App\Enums\DocumentStatus;
use App\Enums\ProfileChangeStatus;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use App\Models\EmployeeDocument;
use App\Models\EmployeeFamilyMember;
use App\Models\EmployeeProfileChangeRequest;
use App\Models\EmploymentHistory;
use App\Models\LegalEntity;
use App\Models\Position;
use App\Models\User;
use App\Models\UserLegalEntityAccess;
use App\Notifications\EmployeeProfileChangeReviewed;
use App\Notifications\EmployeeProfileChangeSubmitted;
use App\Services\Employee\EmployeeManager;
use App\Services\Organization\OrganizationStructureManager;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function phaseSixUser(string $role = 'Super Admin'): User
{
    $user = User::factory()->create(['password' => Hash::make('ValidPassword!2026')]);
    $user->assignRole($role);

    return $user;
}

/** @return array{admin: User, entity: LegalEntity, branch: Branch, department: Department, position: Position, employee: Employee} */
function phaseSixContext(string $code = 'DUN'): array
{
    $admin = phaseSixUser();
    $organization = app(OrganizationStructureManager::class);
    $entity = $organization->createLegalEntity($admin, [
        'code' => $code,
        'legal_name' => 'PT '.$code,
        'display_name' => $code.' Company',
        'country_code' => 'ID',
        'timezone' => 'Asia/Jakarta',
        'currency' => 'IDR',
        'status' => 'active',
    ]);
    $branch = $organization->createUnit($admin, $entity, 'branches', [
        'code' => $code.'-BR', 'name' => $code.' Branch', 'status' => 'active', 'timezone' => 'Asia/Jakarta',
    ]);
    $department = $organization->createUnit($admin, $entity, 'departments', [
        'code' => $code.'-DEP', 'name' => $code.' Department', 'status' => 'active',
        'branch_public_id' => $branch->public_id,
    ]);
    $position = $organization->createUnit($admin, $entity, 'positions', [
        'code' => $code.'-POS', 'name' => $code.' Position', 'status' => 'active',
        'department_public_id' => $department->public_id,
    ]);
    $employee = app(EmployeeManager::class)->create($admin, $entity, [
        'legal_entity_public_id' => $entity->public_id,
        'employee_number' => $code.'-001',
        'full_name' => 'Ayu Pratama',
        'nik' => '3174000000000001',
        'birth_place' => 'Jakarta',
        'birth_date' => '1990-01-15',
        'gender' => 'female',
        'marital_status' => 'married',
        'personal_email' => 'ayu.personal@example.test',
        'company_email' => 'ayu@example.test',
        'phone' => '081234567890',
        'address' => 'Original private address',
        'emergency_name' => 'Budi Pratama',
        'emergency_relationship' => 'Spouse',
        'emergency_phone' => '081298765432',
        'emergency_address' => 'Original emergency address',
        'branch_public_id' => $branch->public_id,
        'department_public_id' => $department->public_id,
        'position_public_id' => $position->public_id,
        'employment_status' => 'permanent',
        'join_date' => '2026-01-01',
        'effective_from' => '2026-01-01',
        'change_reason' => 'Initial onboarding',
        'contract_type' => 'permanent',
        'contract_number' => $code.'-001-CTR',
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
    ]);

    return compact('admin', 'entity', 'branch', 'department', 'position', 'employee');
}

function phaseSixEmployeeUser(Employee $employee, string $email = 'employee@example.test'): User
{
    $user = phaseSixUser('Employee');
    $user->forceFill(['employee_id' => $employee->getKey(), 'email' => $email])->save();

    return $user;
}

function phaseSixReviewer(User $grantor, LegalEntity $entity): User
{
    $reviewer = phaseSixUser('Company HR Admin');
    UserLegalEntityAccess::query()->create([
        'user_id' => $reviewer->getKey(),
        'legal_entity_id' => $entity->getKey(),
        'access_level' => 'manage',
        'effective_from' => '2026-01-01',
        'granted_by' => $grantor->getKey(),
        'reason' => 'Phase 6 HR reviewer.',
    ]);

    return $reviewer;
}

/** @return array<string, mixed> */
function phaseSixBankRequestPayload(): array
{
    return [
        'request_type' => 'bank_account',
        'reason' => 'Payroll account has changed after bank verification.',
        'bank_code' => 'MANDIRI',
        'bank_name' => 'Bank Mandiri',
        'account_number' => '112233445566',
        'account_holder_name' => 'Ayu Pratama',
        'effective_from' => now()->addDay()->toDateString(),
        'attachment' => UploadedFile::fake()->create('bank-proof.pdf', 12, 'application/pdf'),
    ];
}

test('phase six schema provides encrypted workflow family history and database notifications', function () {
    expect(Schema::hasTable('employee_family_members'))->toBeTrue()
        ->and(Schema::hasTable('employee_profile_change_requests'))->toBeTrue()
        ->and(Schema::hasTable('notifications'))->toBeTrue()
        ->and(Schema::hasColumn('employee_profile_change_requests', 'snapshot_fingerprint'))->toBeTrue()
        ->and(Schema::hasColumn('employee_profile_change_requests', 'manual_follow_up_required'))->toBeTrue();
});

test('a linked employee can open only their own ESS profile without administrative entity scope', function () {
    $context = phaseSixContext();
    $employeeUser = phaseSixEmployeeUser($context['employee']);
    $unlinkedUser = phaseSixUser('Employee');

    expect(UserLegalEntityAccess::query()->where('user_id', $employeeUser->getKey())->exists())->toBeFalse();

    $this->actingAs($employeeUser)->get(route('ess.dashboard'))
        ->assertOk()
        ->assertSee('Ayu Pratama')
        ->assertSee('DUN Company')
        ->assertDontSee('3174000000000001')
        ->assertDontSee('444455556666');
    $this->actingAs($unlinkedUser)->get(route('ess.dashboard'))->assertForbidden();
});

test('direct ESS contact updates preserve effective history and encrypted storage', function () {
    $context = phaseSixContext();
    $employeeUser = phaseSixEmployeeUser($context['employee']);

    $this->actingAs($employeeUser)->put(route('ess.profile.contact.update'), [
        'phone' => '081200001111',
        'address' => 'Updated private address',
        'emergency_name' => 'Citra Pratama',
        'emergency_relationship' => 'Sibling',
        'emergency_phone' => '081200002222',
        'emergency_address' => 'Updated emergency address',
    ])->assertRedirect(route('ess.dashboard'))->assertSessionHasNoErrors();

    $rawPhone = DB::table('employee_contacts')
        ->where('employee_id', $context['employee']->getKey())
        ->where('type', 'phone')->latest('effective_from')->first();
    $rawEmergency = DB::table('emergency_contacts')
        ->where('employee_id', $context['employee']->getKey())->latest('effective_from')->first();

    expect(DB::table('employee_contacts')->where('employee_id', $context['employee']->getKey())->where('type', 'phone')->count())->toBe(2)
        ->and((string) $rawPhone->value)->not->toContain('081200001111')
        ->and((string) $rawEmergency->phone)->not->toContain('081200002222')
        ->and(DB::table('employee_contacts')->where('employee_id', $context['employee']->getKey())->where('type', 'phone')->whereDate('effective_to', now()->toDateString())->exists())->toBeTrue();
});

test('sensitive profile requests encrypt old proposed and reason values and notify only scoped reviewers', function () {
    Storage::fake('local');
    Notification::fake();
    $context = phaseSixContext();
    $employeeUser = phaseSixEmployeeUser($context['employee']);
    $reviewer = phaseSixReviewer($context['admin'], $context['entity']);
    $otherContext = phaseSixContext('OTHER');
    $outsideReviewer = phaseSixReviewer($otherContext['admin'], $otherContext['entity']);

    $this->actingAs($employeeUser)->post(route('ess.requests.store'), [
        'request_type' => 'legal_name',
        'reason' => 'Official civil registry name has been corrected.',
        'full_name' => 'Ayu Pratama Resmi',
        'attachment' => UploadedFile::fake()->create('identity-proof.pdf', 12, 'application/pdf'),
    ])->assertRedirect()->assertSessionHasNoErrors();

    $changeRequest = EmployeeProfileChangeRequest::query()->sole();
    $raw = DB::table('employee_profile_change_requests')->find($changeRequest->getKey());
    $document = EmployeeDocument::query()->sole();

    expect((string) $raw->proposed_values)->not->toContain('Ayu Pratama Resmi')
        ->and((string) $raw->current_values)->not->toContain('Ayu Pratama')
        ->and((string) $raw->reason)->not->toContain('civil registry')
        ->and($document->status)->toBe(DocumentStatus::PendingReview)
        ->and($document->storage_path)->not->toContain('identity-proof');
    Notification::assertSentTo($reviewer, EmployeeProfileChangeSubmitted::class);
    Notification::assertNotSentTo($outsideReviewer, EmployeeProfileChangeSubmitted::class);
});

test('duplicate pending requests of the same type are rejected without orphaning private files', function () {
    Storage::fake('local');
    $context = phaseSixContext();
    $employeeUser = phaseSixEmployeeUser($context['employee']);

    $this->actingAs($employeeUser)->post(route('ess.requests.store'), phaseSixBankRequestPayload())
        ->assertSessionHasNoErrors();
    $this->actingAs($employeeUser)->post(route('ess.requests.store'), phaseSixBankRequestPayload())
        ->assertSessionHasErrors('request_type');

    expect(EmployeeProfileChangeRequest::query()->count())->toBe(1)
        ->and(EmployeeDocument::query()->count())->toBe(1)
        ->and(Storage::disk('local')->allFiles('employee-documents/'.$context['employee']->public_id))->toHaveCount(1);
});

test('a scoped HR review applies an effective dated bank change and notifies the requester', function () {
    Storage::fake('local');
    $context = phaseSixContext();
    $employeeUser = phaseSixEmployeeUser($context['employee']);
    $reviewer = phaseSixReviewer($context['admin'], $context['entity']);
    $effectiveFrom = now()->addDay()->toDateString();

    $this->actingAs($employeeUser)->post(route('ess.requests.store'), phaseSixBankRequestPayload())
        ->assertSessionHasNoErrors();
    $changeRequest = EmployeeProfileChangeRequest::query()->sole();

    $this->actingAs($reviewer)->put(route('ess.review.update', $changeRequest), [
        'decision' => 'approve',
        'review_notes' => 'Bank proof verified against the employee name.',
    ])->assertRedirect(route('ess.review.index'))->assertSessionHasNoErrors();

    $newAccount = EmployeeBankAccount::query()->whereDate('effective_from', $effectiveFrom)->sole();
    $rawAccount = DB::table('employee_bank_accounts')->find($newAccount->getKey());

    expect($changeRequest->refresh()->changeStatus())->toBe(ProfileChangeStatus::Approved)
        ->and($changeRequest->applied_at)->not->toBeNull()
        ->and(EmployeeBankAccount::query()->count())->toBe(2)
        ->and(EmployeeBankAccount::query()->oldest('effective_from')->firstOrFail()->effective_to?->toDateString())->toBe($effectiveFrom)
        ->and((string) $rawAccount->account_number)->not->toContain('112233445566')
        ->and($employeeUser->notifications()->where('type', EmployeeProfileChangeReviewed::class)->exists())->toBeTrue();
});

test('stale profile snapshots block approval without overwriting newer master data', function () {
    Storage::fake('local');
    $context = phaseSixContext();
    $employeeUser = phaseSixEmployeeUser($context['employee']);
    $reviewer = phaseSixReviewer($context['admin'], $context['entity']);

    $this->actingAs($employeeUser)->post(route('ess.requests.store'), [
        'request_type' => 'legal_name',
        'reason' => 'Request based on an earlier identity record.',
        'full_name' => 'Proposed Employee Name',
        'attachment' => UploadedFile::fake()->create('identity.pdf', 10, 'application/pdf'),
    ])->assertSessionHasNoErrors();
    $changeRequest = EmployeeProfileChangeRequest::query()->sole();
    $context['employee']->update(['full_name' => 'Newer HR Master Name', 'updated_by' => $context['admin']->getKey()]);

    $this->actingAs($reviewer)->put(route('ess.review.update', $changeRequest), [
        'decision' => 'approve',
        'review_notes' => 'Attempt to approve a stale request.',
    ])->assertSessionHasErrors('decision');

    expect($changeRequest->refresh()->changeStatus())->toBe(ProfileChangeStatus::Pending)
        ->and($context['employee']->refresh()->full_name)->toBe('Newer HR Master Name');
});

test('review visibility fails closed for HR reviewers from another legal entity', function () {
    Storage::fake('local');
    $context = phaseSixContext();
    $employeeUser = phaseSixEmployeeUser($context['employee']);
    $otherContext = phaseSixContext('OTHER');
    $outsideReviewer = phaseSixReviewer($otherContext['admin'], $otherContext['entity']);

    $this->actingAs($employeeUser)->post(route('ess.requests.store'), [
        'request_type' => 'marital_status',
        'reason' => 'Civil status evidence has changed recently.',
        'marital_status' => 'single',
        'attachment' => UploadedFile::fake()->create('civil-status.pdf', 10, 'application/pdf'),
    ])->assertSessionHasNoErrors();
    $changeRequest = EmployeeProfileChangeRequest::query()->sole();

    $this->actingAs($outsideReviewer)->get(route('ess.requests.show', $changeRequest))->assertNotFound();
    $this->actingAs($outsideReviewer)->put(route('ess.review.update', $changeRequest), [
        'decision' => 'approve', 'review_notes' => 'Unauthorized cross-company review.',
    ])->assertNotFound();
});

test('approved family requests create encrypted family records while employment corrections require manual HR follow up', function () {
    $context = phaseSixContext();
    $employeeUser = phaseSixEmployeeUser($context['employee']);
    $reviewer = phaseSixReviewer($context['admin'], $context['entity']);

    $this->actingAs($employeeUser)->post(route('ess.requests.store'), [
        'request_type' => 'family_data',
        'reason' => 'Add a verified dependent to the employee profile.',
        'family_full_name' => 'Private Child Name',
        'relationship' => 'child',
        'birth_date' => '2020-01-15',
        'identity_number' => '3174000000000099',
        'effective_from' => now()->toDateString(),
    ])->assertSessionHasNoErrors();
    $familyRequest = EmployeeProfileChangeRequest::query()->sole();
    $this->actingAs($reviewer)->put(route('ess.review.update', $familyRequest), [
        'decision' => 'approve', 'review_notes' => 'Family evidence verified.',
    ])->assertSessionHasNoErrors();

    $family = EmployeeFamilyMember::query()->sole();
    $rawFamily = DB::table('employee_family_members')->find($family->getKey());
    expect((string) $rawFamily->full_name)->not->toContain('Private Child Name')
        ->and((string) $rawFamily->identity_number)->not->toContain('3174000000000099');

    $historyCount = EmploymentHistory::query()->where('employee_id', $context['employee']->getKey())->count();
    $this->actingAs($employeeUser)->post(route('ess.requests.store'), [
        'request_type' => 'employment_data',
        'reason' => 'The displayed department assignment needs HR verification.',
        'requested_change' => 'Please verify the department and direct manager shown in my profile.',
        'preferred_effective_date' => now()->toDateString(),
    ])->assertSessionHasNoErrors();
    $employmentRequest = EmployeeProfileChangeRequest::query()->where('type', 'employment_data')->sole();
    $this->actingAs($reviewer)->put(route('ess.review.update', $employmentRequest), [
        'decision' => 'approve', 'review_notes' => 'Accepted for controlled HR master-data follow-up.',
    ])->assertSessionHasNoErrors();

    expect($employmentRequest->refresh()->changeStatus())->toBe(ProfileChangeStatus::Approved)
        ->and($employmentRequest->manual_follow_up_required)->toBeTrue()
        ->and($employmentRequest->applied_at)->toBeNull()
        ->and(EmploymentHistory::query()->where('employee_id', $context['employee']->getKey())->count())->toBe($historyCount);
});

test('notification ownership and private evidence downloads cannot be crossed between employees', function () {
    Storage::fake('local');
    $first = phaseSixContext('ONE');
    $firstUser = phaseSixEmployeeUser($first['employee'], 'one@example.test');
    $second = phaseSixContext('TWO');
    $secondUser = phaseSixEmployeeUser($second['employee'], 'two@example.test');

    $this->actingAs($firstUser)->post(route('ess.requests.store'), [
        'request_type' => 'legal_name',
        'reason' => 'Official identity requires a corrected legal name.',
        'full_name' => 'Corrected Name One',
        'attachment' => UploadedFile::fake()->create('identity-one.pdf', 10, 'application/pdf'),
    ])->assertSessionHasNoErrors();
    $changeRequest = EmployeeProfileChangeRequest::query()->sole();
    $document = $changeRequest->attachmentDocument;
    expect($document)->toBeInstanceOf(EmployeeDocument::class);

    $firstUser->notify(new EmployeeProfileChangeReviewed($changeRequest));
    $notification = $firstUser->notifications()->sole();

    $this->actingAs($secondUser)->patch(route('notifications.read', $notification->id))->assertNotFound();
    $this->actingAs($secondUser)->get(route('employee-documents.download', $document))->assertNotFound();
    $this->actingAs($firstUser)->get(route('employee-documents.download', $document))->assertOk();
    $this->actingAs($firstUser)->patch(route('notifications.read', $notification->id))->assertRedirect();
    expect($notification->refresh()->read_at)->not->toBeNull();
});

test('ESS interfaces render from Indonesian and English translation keys', function () {
    $context = phaseSixContext();
    $employeeUser = phaseSixEmployeeUser($context['employee']);

    $this->actingAs($employeeUser)->withSession(['locale' => 'id'])->get(route('ess.dashboard'))
        ->assertOk()->assertSee('Pembaruan kontak langsung')->assertSee('Ajukan perubahan profil');
    $this->actingAs($employeeUser)->withSession(['locale' => 'en'])->get(route('ess.dashboard'))
        ->assertOk()->assertSee('Direct contact update')->assertSee('Submit a profile change');
});

test('the phase six review package is complete and contains no open placeholders', function () {
    $documents = [
        'ess-scope-and-access.md',
        'profile-change-workflow.md',
        'notifications-and-private-evidence.md',
        'operations-runbook.md',
        'phase-6-exit-review.md',
    ];

    foreach ($documents as $document) {
        $contents = file_get_contents(base_path('docs/06-employee-self-service/'.$document));

        expect($contents)->toBeString()
            ->not->toContain('[TODO]', '[TBD]')
            ->and(strlen((string) $contents))->toBeGreaterThan(500);
    }
});
