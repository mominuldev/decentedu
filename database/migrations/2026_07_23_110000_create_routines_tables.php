<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Timetable slots (e.g. "Period 1", 09:00-09:45) — scoped to a shift, shared by
        // routines (which period a class meets in) and attendance (period-wise take).
        Schema::create('periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('serial')->default(0);
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'shift_id', 'name'], 'period_unique');
        });

        // Class routine: class_config x day x period -> subject + teacher (+ room).
        Schema::create('class_routines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_config_id')->constrained('class_configs')->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('periods')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0=Sunday .. 6=Saturday (PHP date('w'))
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('room')->nullable();
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            // One subject slot per class per day per period.
            $table->unique(['branch_id', 'class_config_id', 'day_of_week', 'period_id'], 'class_routine_slot_unique');
            // Fast conflict lookups (teacher / room double-booking).
            $table->index(['branch_id', 'employee_id', 'day_of_week', 'period_id'], 'class_routine_teacher_index');
            $table->index(['branch_id', 'room', 'day_of_week', 'period_id'], 'class_routine_room_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_routines');
        Schema::dropIfExists('periods');
    }
};
