<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_rules', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->string('day_type', 32);
            $table->string('calculation_method', 24);
            $table->unsignedInteger('minimum_minutes')->default(0);
            $table->unsignedInteger('rounding_increment_minutes')->default(1);
            $table->string('rounding_mode', 16)->default('floor');
            $table->unsignedInteger('maximum_minutes');
            $table->json('segment_rules');
            $table->unsignedInteger('meal_threshold_minutes')->nullable();
            $table->unsignedBigInteger('meal_allowance_idr')->default(0);
            $table->unsignedInteger('transport_threshold_minutes')->nullable();
            $table->unsignedBigInteger('transport_allowance_idr')->default(0);
            $table->string('eligibility', 32)->default('all_active');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 16)->default('active');
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['legal_entity_id', 'code', 'effective_from'], 'overtime_rule_version_unique');
            $table->index(['legal_entity_id', 'day_type', 'status', 'effective_from'], 'overtime_rule_effective_idx');
        });

        Schema::create('overtime_requests', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('overtime_rule_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('request_type', 24);
            $table->string('day_type_snapshot', 32);
            $table->date('work_date');
            $table->timestamp('planned_start_at');
            $table->timestamp('planned_end_at');
            $table->unsignedInteger('planned_minutes');
            $table->unsignedInteger('approved_minutes')->nullable();
            $table->foreignId('attendance_record_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('actual_start_at')->nullable();
            $table->timestamp('actual_end_at')->nullable();
            $table->unsignedInteger('actual_minutes')->nullable();
            $table->unsignedInteger('payable_minutes')->nullable();
            $table->unsignedBigInteger('weighted_minutes_hundredths')->nullable();
            $table->boolean('meal_eligible')->default(false);
            $table->unsignedBigInteger('meal_allowance_idr')->default(0);
            $table->boolean('transport_eligible')->default(false);
            $table->unsignedBigInteger('transport_allowance_idr')->default(0);
            $table->longText('reason');
            $table->longText('work_description');
            $table->longText('validation_note')->nullable();
            $table->string('status', 32)->default('pending_manager');
            $table->foreignId('approval_instance_id')->nullable()->constrained()->restrictOnDelete();
            $table->char('request_fingerprint', 64)->unique();
            $table->string('payroll_period_key', 7)->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('payroll_eligible_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['legal_entity_id', 'status', 'work_date'], 'overtime_request_queue_idx');
            $table->index(['employee_id', 'work_date', 'status'], 'overtime_request_employee_idx');
        });

        Schema::create('overtime_calculations', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('overtime_request_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('overtime_rule_id')->constrained()->restrictOnDelete();
            $table->foreignId('attendance_record_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('planned_minutes');
            $table->unsignedInteger('approved_minutes');
            $table->unsignedInteger('actual_minutes');
            $table->unsignedInteger('payable_minutes');
            $table->unsignedBigInteger('weighted_minutes_hundredths');
            $table->boolean('meal_eligible');
            $table->unsignedBigInteger('meal_allowance_idr');
            $table->boolean('transport_eligible');
            $table->unsignedBigInteger('transport_allowance_idr');
            $table->boolean('payroll_eligible')->default(false);
            $table->json('rule_snapshot');
            $table->json('calculation_trace');
            $table->char('rule_checksum', 64);
            $table->foreignId('calculated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->index(['legal_entity_id', 'payroll_eligible', 'calculated_at'], 'overtime_calculation_payroll_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_calculations');
        Schema::dropIfExists('overtime_requests');
        Schema::dropIfExists('overtime_rules');
    }
};
