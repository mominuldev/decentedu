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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');

            // Employee identification
            $table->string('employee_uid')->unique(); // branch-unique employee ID
            $table->string('name');
            $table->string('name_bn')->nullable();

            // Job details
            $table->foreignId('designation_id')->constrained('designations')->onDelete('restrict');
            $table->foreignId('hr_section_id')->nullable()->constrained('hr_sections')->onDelete('restrict');

            // Personal details
            $table->enum('sex', ['male', 'female', 'other']);
            $table->string('religion')->nullable();
            $table->date('dob')->nullable();
            $table->string('mobile')->nullable();
            $table->string('email')->nullable();
            $table->string('nid')->nullable();

            // Address and photo
            $table->string('photo_path')->nullable();
            $table->text('present_address')->nullable();
            $table->text('permanent_address')->nullable();

            // Employment details
            $table->date('joining_date');
            $table->date('leaving_date')->nullable();
            $table->enum('employment_type', ['permanent', 'contract', 'temporary'])->default('permanent');
            $table->enum('status', ['active', 'resigned', 'terminated', 'retired'])->default('active');

            // Educational qualifications (can be JSON or separate table)
            $table->json('qualifications')->nullable();

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index(['branch_id', 'status']);
            $table->unique(['branch_id', 'employee_uid']);
            $table->index(['branch_id', 'mobile']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
