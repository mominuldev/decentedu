<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Online Admission module (docs/02 §Admission, docs/04). The legacy setup screens 500'd in the
 * captured demo, so the exact schema is [inferred] from the blueprint: an admission drive
 * (admission_years) opens applications for prospective students, each optionally tied to a seat
 * quota; applications move pending → selected/waiting/rejected → admitted, and an admitted
 * applicant is converted into a Student + Enrollment. quota_configs (per-class seat caps) is
 * folded into a capacity column on quotas + the year to keep the first cut tractable.
 */
return new class extends Migration
{
    public function up(): void
    {
        // An admission drive, usually aligned to an academic year/session.
        Schema::create('admission_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->string('title'); // e.g. "Admission 2026"
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->unsignedInteger('serial')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['branch_id', 'title'], 'admission_year_title_unique');
            $table->index(['branch_id', 'status'], 'admission_year_status_index');
        });

        // Seat quotas / reservation categories (General, Freedom Fighter, Sibling, …).
        Schema::create('quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedInteger('capacity')->nullable(); // null = uncapped
            $table->boolean('status')->default(true);
            $table->unsignedInteger('serial')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['branch_id', 'name'], 'quota_name_unique');
        });

        // A prospective student's application within a drive.
        Schema::create('admission_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->foreignId('admission_year_id')->constrained('admission_years')->onDelete('cascade');
            $table->foreignId('class_config_id')->constrained('class_configs')->onDelete('cascade');
            $table->foreignId('quota_id')->nullable()->constrained('quotas')->nullOnDelete();

            // Applicant bio (mirrors the fields captured on Student for a clean conversion).
            $table->string('application_no'); // branch-unique tracking number
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->enum('sex', ['male', 'female', 'other']);
            $table->enum('religion', ['islam', 'hindu', 'christian', 'buddhist', 'others'])->nullable();
            $table->string('blood_group')->nullable();
            $table->date('dob')->nullable();
            $table->string('birth_certificate_no')->nullable();
            $table->string('fathers_name');
            $table->string('father_nid')->nullable();
            $table->string('father_mobile')->nullable();
            $table->string('mothers_name');
            $table->string('mother_nid')->nullable();
            $table->string('mother_mobile')->nullable();
            $table->string('mobile')->nullable();
            $table->string('guardian_mobile')->nullable();
            $table->string('photo_path')->nullable();
            $table->text('present_address')->nullable();
            $table->text('permanent_address')->nullable();

            // Selection: merit score + pipeline status; student_id is set once converted.
            $table->decimal('score', 8, 2)->nullable();
            $table->enum('status', ['pending', 'selected', 'waiting', 'rejected', 'admitted'])->default('pending');
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->date('applied_at')->nullable();
            $table->string('remarks')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['branch_id', 'application_no'], 'application_no_unique');
            $table->index(['branch_id', 'admission_year_id', 'status'], 'application_year_status_index');
            $table->index(['branch_id', 'class_config_id'], 'application_class_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_applications');
        Schema::dropIfExists('quotas');
        Schema::dropIfExists('admission_years');
    }
};
