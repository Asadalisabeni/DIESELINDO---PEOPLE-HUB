<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_components', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->string('type', 16);
            $table->string('calculation_type', 32);
            $table->boolean('taxable')->default(false);
            $table->boolean('bpjs_eligible')->default(false);
            $table->char('currency', 3)->default('IDR');
            $table->unsignedTinyInteger('rounding_scale')->default(0);
            $table->string('rounding_mode', 16)->default('nearest');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 16)->default('active');
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['legal_entity_id', 'code', 'effective_from'], 'salary_component_version_unique');
            $table->index(['legal_entity_id', 'status', 'effective_from', 'effective_to'], 'salary_component_effective_idx');
        });

        Schema::create('salary_histories', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->char('currency', 3)->default('IDR');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 16)->default('draft');
            $table->text('reason');
            $table->decimal('monthly_income_total', 19, 4)->default(0);
            $table->decimal('monthly_deduction_total', 19, 4)->default(0);
            $table->char('version_checksum', 64);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'effective_from'], 'salary_history_employee_start_unique');
            $table->index(['legal_entity_id', 'employee_id', 'status', 'effective_from', 'effective_to'], 'salary_history_effective_idx');
        });

        Schema::create('employee_salary_components', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('salary_history_id')->constrained()->restrictOnDelete();
            $table->foreignId('salary_component_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->decimal('amount', 19, 4);
            $table->decimal('quantity', 12, 4)->default(1);
            $table->string('input_reference', 100)->nullable();
            $table->timestamps();
            $table->unique(['salary_history_id', 'salary_component_id', 'sequence'], 'employee_salary_component_unique');
            $table->index(['legal_entity_id', 'salary_component_id'], 'employee_salary_component_lookup_idx');
        });

        Schema::create('payroll_groups', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->string('frequency', 16)->default('monthly');
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->char('currency', 3)->default('IDR');
            $table->string('proration_basis', 24)->default('calendar_days');
            $table->unsignedTinyInteger('cutoff_start_day');
            $table->unsignedTinyInteger('cutoff_end_day');
            $table->unsignedTinyInteger('payment_day');
            $table->string('payment_date_adjustment', 24)->default('previous_working_day');
            $table->string('status', 16)->default('active');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['legal_entity_id', 'code'], 'payroll_group_entity_code_unique');
        });

        Schema::create('payroll_group_memberships', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_group_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->text('reason');
            $table->string('source', 24)->default('manual');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['employee_id', 'effective_from'], 'payroll_membership_start_unique');
            $table->index(['legal_entity_id', 'payroll_group_id', 'effective_from', 'effective_to'], 'payroll_membership_effective_idx');
        });

        Schema::create('payroll_periods', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_group_id')->constrained()->restrictOnDelete();
            $table->string('period_key', 7);
            $table->string('period_type', 24)->default('monthly');
            $table->date('payroll_start');
            $table->date('payroll_end');
            $table->date('attendance_cutoff_start');
            $table->date('attendance_cutoff_end');
            $table->date('payment_date');
            $table->string('status', 16)->default('open');
            $table->json('calendar_snapshot');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['payroll_group_id', 'period_key', 'period_type'], 'payroll_period_unique');
            $table->index(['legal_entity_id', 'status', 'payment_date'], 'payroll_period_status_payment_idx');
        });

        Schema::create('payroll_runs', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->restrictOnDelete();
            $table->string('run_type', 24)->default('regular');
            $table->unsignedSmallInteger('version');
            $table->string('status', 24)->default('draft');
            $table->char('currency', 3)->default('IDR');
            $table->timestamp('source_snapshot_at')->nullable();
            $table->decimal('gross_total', 19, 4)->default(0);
            $table->decimal('deduction_total', 19, 4)->default(0);
            $table->decimal('employer_total', 19, 4)->default(0);
            $table->decimal('tax_total', 19, 4)->default(0);
            $table->decimal('bpjs_total', 19, 4)->default(0);
            $table->decimal('net_total', 19, 4)->default(0);
            $table->json('validation_summary')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('calculated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();
            $table->unique(['payroll_period_id', 'run_type', 'version'], 'payroll_run_version_unique');
            $table->index(['legal_entity_id', 'status', 'created_at']);
        });

        Schema::create('payroll_run_employees', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_run_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('employment_history_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('salary_history_id')->nullable()->constrained()->restrictOnDelete();
            $table->json('employee_snapshot');
            $table->text('bank_snapshot')->nullable();
            $table->json('salary_snapshot');
            $table->date('service_from');
            $table->date('service_to');
            $table->unsignedSmallInteger('payable_days');
            $table->unsignedSmallInteger('period_days');
            $table->decimal('gross_total', 19, 4)->default(0);
            $table->decimal('deduction_total', 19, 4)->default(0);
            $table->decimal('employer_total', 19, 4)->default(0);
            $table->decimal('tax_total', 19, 4)->default(0);
            $table->decimal('bpjs_total', 19, 4)->default(0);
            $table->decimal('net_total', 19, 4)->default(0);
            $table->string('validation_status', 16)->default('pending');
            $table->char('snapshot_checksum', 64);
            $table->timestamps();
            $table->unique(['payroll_run_id', 'employee_id'], 'payroll_run_employee_unique');
            $table->index(['legal_entity_id', 'payroll_run_id', 'validation_status'], 'payroll_run_employee_validation_idx');
        });

        Schema::create('payroll_items', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_run_employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('salary_component_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('component_code', 40);
            $table->string('component_name');
            $table->string('component_type', 16);
            $table->string('calculation_type', 32);
            $table->decimal('quantity', 12, 4)->default(1);
            $table->decimal('rate', 19, 4)->default(0);
            $table->decimal('base_amount', 19, 4)->default(0);
            $table->decimal('unrounded_amount', 19, 4)->default(0);
            $table->decimal('amount', 19, 4);
            $table->char('currency', 3)->default('IDR');
            $table->string('source_type', 40);
            $table->string('source_reference', 100)->nullable();
            $table->json('calculation_metadata');
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->timestamps();
            $table->unique(['payroll_run_employee_id', 'component_code', 'sequence'], 'payroll_item_unique');
            $table->index(['legal_entity_id', 'component_type', 'component_code'], 'payroll_item_component_lookup_idx');
        });

        Schema::create('payroll_validation_findings', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_run_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_run_employee_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('severity', 16);
            $table->string('code', 80);
            $table->string('message_key', 150);
            $table->text('details')->nullable();
            $table->string('status', 16)->default('open');
            $table->timestamps();
            $table->unique(['payroll_run_id', 'payroll_run_employee_id', 'code'], 'payroll_finding_unique');
            $table->index(['legal_entity_id', 'payroll_run_id', 'severity', 'status'], 'payroll_finding_queue_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_validation_findings');
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll_run_employees');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('payroll_group_memberships');
        Schema::dropIfExists('payroll_groups');
        Schema::dropIfExists('employee_salary_components');
        Schema::dropIfExists('salary_histories');
        Schema::dropIfExists('salary_components');
    }
};
