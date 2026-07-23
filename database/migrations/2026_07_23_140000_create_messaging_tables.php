<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['attendance', 'result', 'fee', 'general', 'custom'])->default('general');
            $table->text('message');
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'name'], 'sms_template_name_unique');
        });

        // Phone book — a contact may optionally be linked back to a student/employee record,
        // or be a free-standing custom entry (e.g. a guardian not otherwise modeled).
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone');
            $table->enum('type', ['student', 'guardian', 'employee', 'custom'])->default('custom');
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'type'], 'contact_type_index');
        });

        // A send request — cost is checked and the branch balance debited before this row is
        // created, so total_cost here is already the committed spend, not an estimate.
        Schema::create('sms_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('sms_templates')->nullOnDelete();
            $table->enum('audience_type', ['class', 'section', 'contact', 'custom_numbers']);
            $table->json('audience_filter')->nullable();
            $table->text('message');
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->enum('status', ['queued', 'processing', 'completed', 'failed'])->default('queued');
            $table->decimal('unit_cost', 6, 2)->default(0);
            $table->decimal('total_cost', 10, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['branch_id', 'status'], 'sms_batch_status_index');
        });

        // The delivery log — one row per recipient per batch.
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('sms_batches')->cascadeOnDelete();
            $table->string('recipient_phone');
            $table->string('recipient_name')->nullable();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->text('message');
            $table->enum('status', ['queued', 'sent', 'failed'])->default('queued');
            $table->text('gateway_response')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'status'], 'sms_message_batch_status_index');
        });

        // Simple balance ledger — one row per branch, debited on send, credited on top-up.
        Schema::create('sms_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('balance', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_balances');
        Schema::dropIfExists('sms_messages');
        Schema::dropIfExists('sms_batches');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('sms_templates');
    }
};
