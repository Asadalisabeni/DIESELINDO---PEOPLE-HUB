<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_entities', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('code', 32)->unique();
            $table->string('legal_name');
            $table->string('display_name');
            $table->text('tax_identifier')->nullable();
            $table->char('tax_identifier_last_four', 4)->nullable();
            $table->char('tax_identifier_blind_index', 64)->nullable()->unique();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->char('country_code', 2)->default('ID');
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->char('currency', 3)->default('IDR');
            $table->string('status', 24)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('user_legal_entity_access', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->string('access_level', 24)->default('manage');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('reason', 500);
            $table->timestamps();

            $table->unique(['user_id', 'legal_entity_id', 'effective_from'], 'user_entity_access_unique');
            $table->index(['user_id', 'effective_from', 'effective_to'], 'user_entity_access_effective_idx');
            $table->index(['legal_entity_id', 'effective_from', 'effective_to'], 'entity_user_access_effective_idx');
        });

        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->char('public_id', 26)->unique();
            $table->string('code', 32);
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->string('status', 24)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['legal_entity_id', 'code']);
            $table->index(['legal_entity_id', 'status', 'name']);
        });

        Schema::create('divisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->char('public_id', 26)->unique();
            $table->string('code', 32);
            $table->string('name');
            $table->string('status', 24)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['legal_entity_id', 'code']);
            $table->index(['legal_entity_id', 'status', 'name']);
        });

        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('division_id')->nullable()->constrained()->restrictOnDelete();
            $table->char('public_id', 26)->unique();
            $table->string('code', 32);
            $table->string('name');
            $table->string('status', 24)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['legal_entity_id', 'code']);
            $table->index(['legal_entity_id', 'branch_id', 'division_id', 'status'], 'departments_hierarchy_idx');
        });

        Schema::create('positions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->char('public_id', 26)->unique();
            $table->string('code', 32);
            $table->string('name');
            $table->string('level', 64)->nullable();
            $table->string('status', 24)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['legal_entity_id', 'code']);
            $table->index(['legal_entity_id', 'department_id', 'status']);
        });

        Schema::create('work_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->char('public_id', 26)->unique();
            $table->string('code', 32);
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->string('status', 24)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['legal_entity_id', 'code']);
            $table->index(['legal_entity_id', 'branch_id', 'status']);
        });

        Schema::create('cost_centers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->char('public_id', 26)->unique();
            $table->string('code', 32);
            $table->string('name');
            $table->string('external_code', 64)->nullable();
            $table->string('status', 24)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['legal_entity_id', 'code']);
            $table->index(['legal_entity_id', 'status', 'name']);
        });

        Schema::create('employees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->char('public_id', 26)->unique();
            $table->string('employee_number', 64);
            $table->string('full_name');
            $table->text('nik')->nullable();
            $table->char('nik_last_four', 4)->nullable();
            $table->char('nik_blind_index', 64)->nullable();
            $table->string('birth_place', 100)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 24)->nullable();
            $table->string('marital_status', 32)->nullable();
            $table->string('personal_email')->nullable();
            $table->string('company_email')->nullable();
            $table->string('status', 24)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['legal_entity_id', 'employee_number']);
            $table->unique(['legal_entity_id', 'nik_blind_index'], 'employees_entity_nik_unique');
            $table->index(['legal_entity_id', 'status', 'full_name']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('employee_id')->nullable()->after('id')->unique()->constrained()->restrictOnDelete();
        });

        Schema::create('employee_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('type', 32);
            $table->text('value');
            $table->boolean('is_primary')->default(true);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'type', 'effective_from']);
            $table->index(['legal_entity_id', 'employee_id', 'type', 'effective_from', 'effective_to'], 'employee_contacts_effective_idx');
        });

        Schema::create('emergency_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('relationship', 64);
            $table->text('phone');
            $table->text('address')->nullable();
            $table->unsignedSmallInteger('priority')->default(1);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'priority', 'effective_from']);
            $table->index(['legal_entity_id', 'employee_id', 'effective_from', 'effective_to'], 'emergency_contacts_effective_idx');
        });

        Schema::create('employment_histories', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('employee_number', 64);
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('division_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('position_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_location_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('manager_employee_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->string('employment_status', 32);
            $table->date('join_date');
            $table->date('termination_date')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('change_reason', 500);
            $table->string('source', 32)->default('manual');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'effective_from']);
            $table->index(['legal_entity_id', 'employee_id', 'effective_from', 'effective_to'], 'employment_histories_effective_idx');
            $table->index(['legal_entity_id', 'branch_id', 'department_id', 'position_id'], 'employment_histories_org_idx');
            $table->index(['legal_entity_id', 'manager_employee_id', 'effective_from', 'effective_to'], 'employment_histories_manager_idx');
        });

        Schema::create('employee_documents', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('type', 64);
            $table->string('storage_disk', 32)->default('local');
            $table->string('storage_path', 500)->unique();
            $table->string('original_name');
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64);
            $table->date('issued_date')->nullable();
            $table->date('expires_date')->nullable();
            $table->string('classification', 24)->default('confidential');
            $table->string('status', 24)->default('valid');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['legal_entity_id', 'employee_id', 'type', 'status'], 'employee_documents_lookup_idx');
            $table->index(['legal_entity_id', 'expires_date', 'status'], 'employee_documents_expiry_idx');
            $table->index(['legal_entity_id', 'checksum_sha256']);
        });

        Schema::create('contracts', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_document_id')->nullable()->constrained('employee_documents')->restrictOnDelete();
            $table->string('contract_type', 32);
            $table->string('contract_number', 64);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('probation_end_date')->nullable();
            $table->string('status', 24)->default('active');
            $table->string('change_reason', 500);
            $table->string('source', 32)->default('manual');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['legal_entity_id', 'contract_number']);
            $table->index(['legal_entity_id', 'employee_id', 'start_date', 'end_date'], 'contracts_effective_idx');
            $table->index(['legal_entity_id', 'end_date', 'status'], 'contracts_expiry_idx');
        });

        Schema::create('employee_bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('bank_code', 16);
            $table->string('bank_name', 100);
            $table->text('account_number');
            $table->char('account_number_last_four', 4);
            $table->char('account_number_blind_index', 64);
            $table->text('account_holder_name');
            $table->string('verification_status', 24)->default('pending');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'effective_from']);
            $table->index(['legal_entity_id', 'employee_id', 'effective_from', 'effective_to'], 'bank_accounts_effective_idx');
            $table->index(['legal_entity_id', 'account_number_blind_index'], 'bank_accounts_blind_idx');
        });

        Schema::create('employee_tax_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->text('tax_identifier')->nullable();
            $table->char('tax_identifier_last_four', 4)->nullable();
            $table->char('tax_identifier_blind_index', 64)->nullable();
            $table->string('ptkp_code', 16)->nullable();
            $table->string('tax_method', 24)->default('gross');
            $table->string('verification_status', 24)->default('pending');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'effective_from']);
            $table->index(['legal_entity_id', 'employee_id', 'effective_from', 'effective_to'], 'tax_profiles_effective_idx');
            $table->index(['legal_entity_id', 'tax_identifier_blind_index'], 'tax_profiles_blind_idx');
        });

        Schema::create('employee_bpjs_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->text('health_number')->nullable();
            $table->char('health_number_last_four', 4)->nullable();
            $table->char('health_number_blind_index', 64)->nullable();
            $table->text('employment_number')->nullable();
            $table->char('employment_number_last_four', 4)->nullable();
            $table->char('employment_number_blind_index', 64)->nullable();
            $table->string('jkk_risk_category', 32)->nullable();
            $table->string('verification_status', 24)->default('pending');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'effective_from']);
            $table->index(['legal_entity_id', 'employee_id', 'effective_from', 'effective_to'], 'bpjs_profiles_effective_idx');
            $table->index(['legal_entity_id', 'health_number_blind_index'], 'bpjs_health_blind_idx');
            $table->index(['legal_entity_id', 'employment_number_blind_index'], 'bpjs_employment_blind_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_bpjs_profiles');
        Schema::dropIfExists('employee_tax_profiles');
        Schema::dropIfExists('employee_bank_accounts');
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('employment_histories');
        Schema::dropIfExists('emergency_contacts');
        Schema::dropIfExists('employee_contacts');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('employee_id');
        });

        Schema::dropIfExists('employees');
        Schema::dropIfExists('cost_centers');
        Schema::dropIfExists('work_locations');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('divisions');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('user_legal_entity_access');
        Schema::dropIfExists('legal_entities');
    }
};
