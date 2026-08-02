<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_family_members', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->text('full_name');
            $table->string('relationship', 48);
            $table->date('birth_date')->nullable();
            $table->text('identity_number')->nullable();
            $table->char('identity_number_last_four', 4)->nullable();
            $table->char('identity_number_blind_index', 64)->nullable();
            $table->string('status', 24)->default('active');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['legal_entity_id', 'employee_id', 'status'], 'family_members_employee_idx');
            $table->index(['legal_entity_id', 'identity_number_blind_index'], 'family_members_identity_idx');
            $table->index(['employee_id', 'effective_from', 'effective_to'], 'family_members_effective_idx');
        });

        Schema::create('employee_profile_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('legal_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('type', 48);
            $table->string('status', 24)->default('pending');
            $table->longText('current_values')->nullable();
            $table->longText('proposed_values');
            $table->char('snapshot_fingerprint', 64);
            $table->longText('reason');
            $table->foreignId('attachment_document_id')->nullable()->constrained('employee_documents')->restrictOnDelete();
            $table->boolean('manual_follow_up_required')->default(false);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->longText('review_notes')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['legal_entity_id', 'status', 'submitted_at'], 'profile_requests_review_queue_idx');
            $table->index(['employee_id', 'status', 'submitted_at'], 'profile_requests_employee_idx');
            $table->index(['requested_by', 'status', 'submitted_at'], 'profile_requests_requester_idx');
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('employee_profile_change_requests');
        Schema::dropIfExists('employee_family_members');
    }
};
