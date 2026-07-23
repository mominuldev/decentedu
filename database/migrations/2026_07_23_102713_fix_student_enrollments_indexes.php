<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the problematic table if it exists
        Schema::dropIfExists('student_enrollments');

        // Recreate with proper index names
        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('academic_year_id')->constrained('academic_years')->onDelete('cascade');
            $table->foreignId('class_config_id')->constrained()->onDelete('cascade');
            $table->foreignId('group_id')->nullable()->constrained('groups')->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained('categories')->onDelete('cascade');

            // Class roll (unique within section per session)
            $table->string('roll');

            // Status flags
            $table->boolean('is_current')->default(true);

            // Enrollment dates
            $table->date('enrolled_at')->default(now());
            $table->date('left_at')->nullable();

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->softDeletes();
            $table->timestamps();

            // Unique constraints with shorter names
            $table->unique(['class_config_id', 'academic_year_id', 'roll'], 'enroll_roll_unique');
            $table->unique(['student_id', 'academic_year_id'], 'student_session_unique');
            $table->index(['branch_id', 'academic_year_id', 'class_config_id'], 'enroll_session_class_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
