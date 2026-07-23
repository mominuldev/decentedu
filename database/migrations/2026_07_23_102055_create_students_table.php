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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');

            // Student identification
            $table->string('student_uid')->unique(); // branch-unique human id / admission no
            $table->string('name');
            $table->string('name_bn')->nullable();

            // Personal details
            $table->enum('sex', ['male', 'female', 'other']);
            $table->string('religion')->nullable();
            $table->string('blood_group')->nullable();
            $table->date('dob')->nullable();

            // Parents/Guardians info
            $table->string('fathers_name');
            $table->string('mothers_name');
            $table->string('mobile')->nullable();
            $table->string('father_mobile')->nullable();
            $table->string('mother_mobile')->nullable();

            // Address and photo
            $table->string('photo_path')->nullable();
            $table->text('present_address')->nullable();
            $table->text('permanent_address')->nullable();

            // Status
            $table->enum('status', ['active', 'transferred', 'left', 'passed_out'])->default('active');

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index(['branch_id', 'status'], 'student_status_index');
            $table->unique(['branch_id', 'student_uid'], 'branch_student_unique');
            $table->index(['branch_id', 'mobile'], 'student_mobile_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
