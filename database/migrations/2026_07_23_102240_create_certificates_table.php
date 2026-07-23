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
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->foreignId('student_id')->constrained()->onDelete('cascade');

            // Certificate details
            $table->enum('certificate_type', ['academic', 'sports', 'cultural', 'attendance', 'other']);
            $table->string('certificate_number')->unique();
            $table->date('issue_date');
            $table->text('description')->nullable();
            $table->text('remarks')->nullable();

            // Academic context (nullable for non-academic certificates)
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years');
            $table->foreignId('class_config_id')->nullable()->constrained('class_configs');

            // Tracking
            $table->string('file_path')->nullable();

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index(['branch_id', 'student_id']);
            $table->index(['branch_id', 'certificate_number']);
            $table->index(['branch_id', 'certificate_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
