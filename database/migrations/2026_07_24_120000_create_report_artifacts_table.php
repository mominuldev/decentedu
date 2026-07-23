<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Backs the queued path of the shared reporting subsystem: a large/batch report is
        // dispatched as a job, the row tracks progress, and the UI polls it for a download link.
        Schema::create('report_artifacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('report_key');
            $table->enum('format', ['pdf', 'excel']);
            $table->json('params')->nullable();
            $table->enum('status', ['pending', 'processing', 'ready', 'failed'])->default('pending');
            $table->string('file_path')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'report_key', 'status'], 'report_artifact_branch_key_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_artifacts');
    }
};
