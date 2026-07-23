<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('title');
            $table->string('name_bn')->nullable();
            $table->enum('type', ['public', 'weekend', 'other'])->default('public');
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'date'], 'holiday_date_unique');
        });

        // Biometric device registry.
        Schema::create('attendance_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('device_uid');
            $table->string('location')->nullable();
            $table->string('ip_address')->nullable();
            $table->enum('protocol', ['zkteco', 'generic'])->default('generic');
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'device_uid'], 'attendance_device_uid_unique');
        });

        // Device's internal user id -> our student/employee.
        Schema::create('attendance_device_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_device_id')->constrained('attendance_devices')->cascadeOnDelete();
            $table->string('external_user_id');
            $table->string('mappable_type');
            $table->unsignedBigInteger('mappable_id');
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'attendance_device_id', 'external_user_id'], 'device_map_unique');
            $table->index(['mappable_type', 'mappable_id'], 'device_map_mappable_index');
        });

        // Time config: expected in/out + grace, per student class_config or for all employees.
        Schema::create('attendance_time_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->enum('applicable_to', ['student', 'employee']);
            $table->foreignId('class_config_id')->nullable()->constrained('class_configs')->cascadeOnDelete();
            $table->time('in_time');
            $table->time('out_time');
            $table->time('late_after');
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'applicable_to', 'class_config_id'], 'time_config_scope_index');
        });

        // Raw punches from a device, before resolution into daily attendance.
        Schema::create('attendance_punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_device_id')->constrained('attendance_devices')->cascadeOnDelete();
            $table->string('external_user_id');
            $table->dateTime('punched_at');
            $table->enum('direction', ['in', 'out'])->nullable();
            $table->json('raw_payload')->nullable();
            $table->boolean('processed')->default(false);
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'processed'], 'punch_processed_index');
            $table->index(['attendance_device_id', 'external_user_id', 'punched_at'], 'punch_lookup_index');
        });

        Schema::create('student_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained('student_enrollments')->nullOnDelete();
            $table->foreignId('class_config_id')->nullable()->constrained('class_configs')->nullOnDelete();
            $table->date('date');
            $table->enum('status', ['present', 'absent', 'late', 'leave', 'half_day'])->default('present');
            $table->time('in_time')->nullable();
            $table->time('out_time')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users');
            $table->enum('source', ['manual', 'device'])->default('manual');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'student_id', 'date'], 'student_attendance_unique');
            $table->index(['branch_id', 'class_config_id', 'date'], 'student_attendance_class_date_index');
        });

        Schema::create('employee_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('date');
            $table->enum('status', ['present', 'absent', 'late', 'leave', 'half_day'])->default('present');
            $table->time('in_time')->nullable();
            $table->time('out_time')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users');
            $table->enum('source', ['manual', 'device'])->default('manual');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'employee_id', 'date'], 'employee_attendance_unique');
            $table->index(['branch_id', 'date'], 'employee_attendance_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_attendances');
        Schema::dropIfExists('student_attendances');
        Schema::dropIfExists('attendance_punches');
        Schema::dropIfExists('attendance_time_configs');
        Schema::dropIfExists('attendance_device_maps');
        Schema::dropIfExists('attendance_devices');
        Schema::dropIfExists('holidays');
    }
};
