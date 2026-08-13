<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->string('category', 32)->default('leave');
            $table->boolean('is_paid')->default(true);
            $table->boolean('requires_balance')->default(true);
            $table->string('unit', 16)->default('day');
            $table->unsignedSmallInteger('evidence_required_from_days')->nullable();
            $table->boolean('requires_payroll_confirmation')->default(false);
            $table->string('status', 24)->default('active');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['legal_entity_id', 'code'], 'leave_types_entity_code_unique');
            $table->index(['legal_entity_id', 'status', 'category'], 'leave_types_lookup_idx');
        });

        Schema::create('leave_policies', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->unsignedSmallInteger('eligibility_months')->default(0);
            $table->decimal('entitlement_quantity', 8, 2)->default(0);
            $table->unsignedSmallInteger('validity_months')->nullable();
            $table->boolean('carry_forward_enabled')->default(false);
            $table->decimal('carry_forward_limit', 8, 2)->nullable();
            $table->unsignedSmallInteger('minimum_notice_days')->default(0);
            $table->decimal('maximum_request_days', 8, 2)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 24)->default('active');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['legal_entity_id', 'leave_type_id', 'version'], 'leave_policy_version_unique');
            $table->index(['legal_entity_id', 'leave_type_id', 'status', 'effective_from', 'effective_to'], 'leave_policy_effective_idx');
        });

        Schema::create('approval_definitions', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('legal_entity_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('key', 100);
            $table->string('subject_type', 64);
            $table->unsignedInteger('version');
            $table->string('risk_class', 24)->default('confidential');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 24)->default('active');
            $table->unsignedSmallInteger('reminder_after_hours')->default(24);
            $table->unsignedSmallInteger('escalation_after_hours')->default(72);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['legal_entity_id', 'key', 'version'], 'approval_definition_version_unique');
            $table->index(['legal_entity_id', 'key', 'status', 'effective_from', 'effective_to'], 'approval_definition_effective_idx');
        });

        Schema::create('approval_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('approval_definition_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('step_order');
            $table->string('name', 100);
            $table->string('resolver_type', 40);
            $table->string('required_permission', 100)->nullable();
            $table->unsignedSmallInteger('minimum_approvals')->default(1);
            $table->unsignedSmallInteger('due_after_hours')->default(24);
            $table->json('conditions')->nullable();
            $table->timestamps();

            $table->unique(['approval_definition_id', 'step_order'], 'approval_step_order_unique');
        });

        Schema::create('approval_instances', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('approval_definition_id')->constrained()->restrictOnDelete();
            $table->string('subject_type', 64);
            $table->char('subject_public_id', 26);
            $table->longText('subject_snapshot');
            $table->char('snapshot_checksum', 64);
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 24)->default('pending');
            $table->unsignedSmallInteger('current_step_order')->nullable();
            $table->char('correlation_id', 26)->unique();
            $table->timestamp('submitted_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['legal_entity_id', 'status', 'submitted_at'], 'approval_instance_queue_idx');
            $table->index(['subject_type', 'subject_public_id', 'status'], 'approval_instance_subject_idx');
        });

        Schema::create('approval_instance_steps', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('approval_instance_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('step_order');
            $table->string('name', 100);
            $table->string('resolver_type', 40);
            $table->longText('resolver_snapshot');
            $table->foreignId('assigned_approver_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('delegated_from_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('required_permission', 100)->nullable();
            $table->string('status', 24)->default('waiting');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['approval_instance_id', 'step_order'], 'approval_instance_step_order_unique');
            $table->index(['status', 'assigned_approver_user_id', 'due_at'], 'approval_instance_step_queue_idx');
        });

        Schema::create('approval_actions', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('approval_instance_id')->constrained()->restrictOnDelete();
            $table->foreignId('approval_instance_step_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('acting_for_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 32);
            $table->longText('note')->nullable();
            $table->foreignId('attachment_document_id')->nullable()->constrained('employee_documents')->restrictOnDelete();
            $table->char('idempotency_hash', 64)->unique();
            $table->timestamp('acted_at');
            $table->timestamps();

            $table->index(['approval_instance_id', 'acted_at'], 'approval_action_history_idx');
        });

        Schema::create('approval_delegations', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('delegator_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('delegate_user_id')->constrained('users')->restrictOnDelete();
            $table->string('subject_type', 64)->nullable();
            $table->date('effective_from');
            $table->date('effective_to');
            $table->longText('reason');
            $table->string('status', 24)->default('active');
            $table->foreignId('granted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['legal_entity_id', 'delegator_user_id', 'status', 'effective_from', 'effective_to'], 'approval_delegation_effective_idx');
        });

        Schema::create('leave_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('leave_policy_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('grant_reference', 100);
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->decimal('opening_quantity', 8, 2);
            $table->string('source', 32)->default('manual');
            $table->string('status', 24)->default('active');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['legal_entity_id', 'grant_reference'], 'leave_entitlement_reference_unique');
            $table->index(['employee_id', 'leave_type_id', 'status', 'valid_from', 'valid_to'], 'leave_entitlement_balance_idx');
        });

        Schema::create('leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('leave_policy_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 8, 2);
            $table->longText('reason');
            $table->foreignId('evidence_document_id')->nullable()->constrained('employee_documents')->restrictOnDelete();
            $table->boolean('is_paid_snapshot');
            $table->boolean('requires_balance_snapshot');
            $table->string('status', 24)->default('pending_manager');
            $table->foreignId('approval_instance_id')->nullable()->constrained()->restrictOnDelete();
            $table->char('request_fingerprint', 64);
            $table->timestamp('submitted_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['legal_entity_id', 'status', 'submitted_at'], 'leave_request_queue_idx');
            $table->index(['employee_id', 'start_date', 'end_date', 'status'], 'leave_request_overlap_idx');
        });

        Schema::create('leave_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('leave_entitlement_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('leave_request_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('entry_type', 24);
            $table->decimal('quantity', 10, 2);
            $table->date('effective_date');
            $table->string('reference_key', 120);
            $table->foreignId('reversal_of_id')->nullable()->constrained('leave_ledger_entries')->restrictOnDelete();
            $table->longText('reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['leave_entitlement_id', 'reference_key'], 'leave_ledger_reference_unique');
            $table->index(['employee_id', 'leave_entitlement_id', 'effective_date'], 'leave_ledger_balance_idx');
            $table->index(['legal_entity_id', 'entry_type', 'effective_date'], 'leave_ledger_report_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_ledger_entries');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_entitlements');
        Schema::dropIfExists('approval_delegations');
        Schema::dropIfExists('approval_actions');
        Schema::dropIfExists('approval_instance_steps');
        Schema::dropIfExists('approval_instances');
        Schema::dropIfExists('approval_steps');
        Schema::dropIfExists('approval_definitions');
        Schema::dropIfExists('leave_policies');
        Schema::dropIfExists('leave_types');
    }
};
