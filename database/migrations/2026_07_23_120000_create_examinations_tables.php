<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Exam list: Weekly / Monthly / Final / Grand Final (confirmed exam types).
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->enum('type', ['weekly', 'monthly', 'final', 'grand_final'])->default('final');
            $table->unsignedInteger('serial')->default(0);
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'name'], 'exam_name_unique');
        });

        // Grade scale, per class, keyed on obtained % (0-100) so it works across subjects
        // regardless of each mark_config's total_marks.
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->string('name'); // A+, A, A-, B, C, D, F
            $table->decimal('grade_point', 3, 2);
            $table->decimal('mark_from', 5, 2);
            $table->decimal('mark_to', 5, 2);
            $table->unsignedInteger('serial')->default(0);
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'class_id'], 'grade_class_index');
        });

        // Mark components a subject's exam can be split into (Written, MCQ, Practical…).
        Schema::create('short_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('serial')->default(0);
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'name'], 'short_code_name_unique');
        });

        // Per class: which exams count, and how merit/position is computed.
        Schema::create('exam_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->enum('merit_basis', ['total_mark', 'grade_point'])->default('total_mark');
            $table->boolean('merit_sequential')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['branch_id', 'class_id'], 'exam_config_class_unique');
        });

        // Which exams feed a class's exam_config (e.g. Grand Final combines two Monthly exams).
        Schema::create('exam_config_exam', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_config_id')->constrained('exam_configs')->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();

            $table->unique(['exam_config_id', 'exam_id'], 'exam_config_exam_unique');
        });

        // class_config x group x exam x subject x short_code -> total/pass mark for that component.
        Schema::create('mark_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_config_id')->constrained('class_configs')->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('short_code_id')->constrained('short_codes')->cascadeOnDelete();
            $table->decimal('total_marks', 6, 2);
            $table->decimal('pass_mark', 6, 2);
            $table->decimal('acceptance', 6, 2)->nullable(); // minimum mark to avoid an auto-fail on this component
            $table->boolean('sc_merge')->default(false); // merge this component into the subject's single displayed total
            $table->unsignedInteger('serial')->default(0);
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['class_config_id', 'group_id', 'exam_id', 'subject_id', 'short_code_id'],
                'mark_config_unique'
            );
            $table->index(['branch_id', 'exam_id', 'class_config_id'], 'mark_config_lookup_index');
        });

        // Per-student assigned 4th/optional subject for a session (Bangladesh-board GPA bonus rule).
        Schema::create('student_fourth_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('class_config_id')->constrained('class_configs')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['branch_id', 'student_id', 'academic_year_id'], 'fourth_subject_unique');
        });

        // The signing "class teacher" for a class_config (appears on marksheets/admit cards).
        Schema::create('class_teacher_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_config_id')->constrained('class_configs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'class_config_id'], 'class_teacher_config_unique');
        });

        // Named signatures placed on printed marksheets/admit cards (principal, controller…).
        Schema::create('signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->enum('position', ['left', 'middle', 'right'])->default('left');
            $table->string('person_name');
            $table->string('designation');
            $table->string('image_path')->nullable();
            $table->unsignedInteger('serial')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'position'], 'signature_position_index');
        });

        // Free-text instructions printed on admit cards — one row per branch.
        Schema::create('admit_instructions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->text('instruction1')->nullable();
            $table->text('instruction2')->nullable();
            $table->text('instruction3')->nullable();
            $table->text('instruction4')->nullable();
            $table->timestamps();

            $table->unique('branch_id', 'admit_instruction_branch_unique');
        });

        // Exam-day schedule: subject x date/time/room (distinct from the weekly class_routines).
        Schema::create('exam_routines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('class_config_id')->constrained('class_configs')->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->date('exam_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('room_no')->nullable();
            $table->string('exam_session')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['exam_id', 'class_config_id', 'group_id', 'subject_id'], 'exam_routine_slot_unique');
            $table->index(['branch_id', 'class_config_id', 'exam_date'], 'exam_routine_date_index');
        });

        // One row per student x mark_config (per subject component per exam).
        Schema::create('marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained('student_enrollments')->nullOnDelete();
            $table->foreignId('mark_config_id')->constrained('mark_configs')->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete(); // denormalised for query speed
            $table->decimal('obtained', 6, 2)->nullable();
            $table->boolean('is_absent')->default(false);
            $table->foreignId('marked_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['student_id', 'mark_config_id'], 'marks_student_config_unique');
            $table->index(['branch_id', 'exam_id', 'student_id'], 'marks_lookup_index');
        });

        // Per-student, per-subject aggregate produced by the "general process" (sums each
        // subject's mark_config components, grades it, decides pass/fail).
        Schema::create('student_exam_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('class_config_id')->constrained('class_configs')->cascadeOnDelete();
            $table->decimal('total_marks', 8, 2)->default(0);
            $table->decimal('obtained_marks', 8, 2)->default(0);
            $table->foreignId('grade_id')->nullable()->constrained('grades')->nullOnDelete();
            $table->decimal('grade_point', 3, 2)->nullable();
            $table->boolean('is_pass')->default(true);
            $table->boolean('is_absent')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'exam_id', 'subject_id'], 'student_exam_result_unique');
            $table->index(['branch_id', 'exam_id', 'class_config_id'], 'student_exam_result_lookup_index');
        });

        // Per-student overall summary produced by the "merit process" (GPA, pass/fail,
        // class/section position). Also the target row for "final process" (combined exams).
        Schema::create('student_exam_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('class_config_id')->constrained('class_configs')->cascadeOnDelete();
            $table->decimal('total_marks', 8, 2)->default(0);
            $table->decimal('total_obtained', 8, 2)->default(0);
            $table->decimal('gpa', 3, 2)->nullable();
            $table->boolean('is_pass')->default(true);
            $table->unsignedInteger('failed_subjects_count')->default(0);
            $table->unsignedInteger('class_position')->nullable();
            $table->unsignedInteger('section_position')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'exam_id'], 'student_exam_summary_unique');
            $table->index(['branch_id', 'exam_id', 'class_config_id'], 'student_exam_summary_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_exam_summaries');
        Schema::dropIfExists('student_exam_results');
        Schema::dropIfExists('marks');
        Schema::dropIfExists('exam_routines');
        Schema::dropIfExists('admit_instructions');
        Schema::dropIfExists('signatures');
        Schema::dropIfExists('class_teacher_configs');
        Schema::dropIfExists('student_fourth_subjects');
        Schema::dropIfExists('mark_configs');
        Schema::dropIfExists('exam_config_exam');
        Schema::dropIfExists('exam_configs');
        Schema::dropIfExists('short_codes');
        Schema::dropIfExists('grades');
        Schema::dropIfExists('exams');
    }
};
