<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_schedules', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->string('timezone', 64);
            $table->unsignedSmallInteger('late_grace_minutes')->default(0);
            $table->unsignedSmallInteger('early_leave_grace_minutes')->default(0);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 24)->default('active');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['legal_entity_id', 'code'], 'work_schedules_entity_code_unique');
            $table->index(['legal_entity_id', 'status', 'effective_from', 'effective_to'], 'work_schedules_effective_idx');
            $table->index(['legal_entity_id', 'branch_id', 'department_id', 'status'], 'work_schedules_scope_idx');
        });

        Schema::create('work_schedule_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_schedule_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->boolean('is_working_day')->default(false);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->boolean('crosses_midnight')->default(false);
            $table->timestamps();

            $table->unique(['work_schedule_id', 'day_of_week'], 'schedule_day_unique');
        });

        Schema::create('employee_schedule_assignments', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_schedule_id')->constrained()->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('source', 32)->default('employee');
            $table->string('reason', 500);
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'effective_from', 'effective_to'], 'employee_schedule_effective_idx');
            $table->index(['legal_entity_id', 'work_schedule_id'], 'entity_schedule_assignment_idx');
        });

        Schema::create('holidays', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('holiday_date');
            $table->string('name');
            $table->string('type', 32)->default('company');
            $table->string('source', 100)->nullable();
            $table->string('status', 24)->default('active');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['legal_entity_id', 'branch_id', 'holiday_date', 'name'], 'holiday_scope_unique');
            $table->index(['legal_entity_id', 'holiday_date', 'status'], 'holiday_lookup_idx');
        });

        Schema::create('attendance_sources', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->string('type', 32);
            $table->string('adapter', 100);
            $table->json('validation_rules')->nullable();
            $table->string('status', 24)->default('active');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['legal_entity_id', 'code'], 'attendance_sources_entity_code_unique');
            $table->index(['legal_entity_id', 'type', 'status'], 'attendance_sources_lookup_idx');
        });

        Schema::create('attendance_events', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('attendance_source_id')->constrained()->restrictOnDelete();
            $table->string('external_event_id', 100);
            $table->char('idempotency_hash', 64);
            $table->string('event_type', 24);
            $table->timestamp('occurred_at');
            $table->timestamp('device_recorded_at')->nullable();
            $table->timestamp('received_at');
            $table->text('latitude')->nullable();
            $table->text('longitude')->nullable();
            $table->unsignedInteger('gps_accuracy_meters')->nullable();
            $table->foreignId('selfie_document_id')->nullable()->constrained('employee_documents')->restrictOnDelete();
            $table->text('activity')->nullable();
            $table->text('destination')->nullable();
            $table->text('notes')->nullable();
            $table->text('device_info')->nullable();
            $table->boolean('was_offline')->default(false);
            $table->string('status', 24);
            $table->json('anomaly_codes')->nullable();
            $table->char('payload_hash', 64);
            $table->string('payroll_eligibility', 24)->default('pending_review');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['attendance_source_id', 'external_event_id'], 'attendance_source_external_unique');
            $table->unique('idempotency_hash');
            $table->index(['legal_entity_id', 'employee_id', 'occurred_at'], 'attendance_events_employee_time_idx');
            $table->index(['legal_entity_id', 'status', 'received_at'], 'attendance_events_review_idx');
        });

        Schema::create('attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_schedule_assignment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('work_schedule_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('work_date');
            $table->timestamp('scheduled_start_at')->nullable();
            $table->timestamp('scheduled_end_at')->nullable();
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('early_leave_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->string('status', 24);
            $table->string('payroll_eligibility', 24)->default('pending_review');
            $table->unsignedInteger('normalization_version');
            $table->foreignId('supersedes_id')->nullable()->constrained('attendance_records')->restrictOnDelete();
            $table->boolean('is_current')->default(true);
            $table->foreignId('normalized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('normalized_at');
            $table->timestamps();

            $table->unique(['legal_entity_id', 'employee_id', 'work_date', 'normalization_version'], 'attendance_record_version_unique');
            $table->index(['legal_entity_id', 'employee_id', 'work_date', 'is_current'], 'attendance_current_record_idx');
            $table->index(['legal_entity_id', 'status', 'work_date'], 'attendance_record_review_idx');
        });

        Schema::create('attendance_record_events', function (Blueprint $table): void {
            $table->foreignId('attendance_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_event_id')->constrained()->restrictOnDelete();
            $table->primary(['attendance_record_id', 'attendance_event_id'], 'attendance_record_event_primary');
        });

        Schema::create('attendance_corrections', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('attendance_record_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('type', 40);
            $table->string('status', 24)->default('pending_manager');
            $table->longText('reason');
            $table->longText('old_values')->nullable();
            $table->longText('proposed_values');
            $table->char('snapshot_fingerprint', 64);
            $table->foreignId('evidence_document_id')->nullable()->constrained('employee_documents')->restrictOnDelete();
            $table->foreignId('manager_reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->longText('manager_review_notes')->nullable();
            $table->timestamp('manager_reviewed_at')->nullable();
            $table->foreignId('hr_reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->longText('hr_review_notes')->nullable();
            $table->timestamp('hr_reviewed_at')->nullable();
            $table->foreignId('applied_record_id')->nullable()->constrained('attendance_records')->restrictOnDelete();
            $table->timestamp('submitted_at');
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['legal_entity_id', 'status', 'submitted_at'], 'attendance_correction_queue_idx');
            $table->index(['employee_id', 'status', 'submitted_at'], 'attendance_correction_employee_idx');
        });

        Schema::create('attendance_import_batches', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('attendance_source_id')->constrained()->restrictOnDelete();
            $table->string('original_filename');
            $table->char('checksum_sha256', 64);
            $table->string('status', 24)->default('processing');
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);
            $table->json('sanitized_errors')->nullable();
            $table->foreignId('imported_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['attendance_source_id', 'checksum_sha256'], 'attendance_import_checksum_unique');
            $table->index(['legal_entity_id', 'status', 'created_at'], 'attendance_import_review_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_import_batches');
        Schema::dropIfExists('attendance_corrections');
        Schema::dropIfExists('attendance_record_events');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance_events');
        Schema::dropIfExists('attendance_sources');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('employee_schedule_assignments');
        Schema::dropIfExists('work_schedule_days');
        Schema::dropIfExists('work_schedules');
    }
};
