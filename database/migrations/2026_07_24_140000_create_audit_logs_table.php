<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->string('action');
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['auditable_type', 'auditable_id'], 'audit_logs_auditable_index');
            $table->index(['branch_id', 'created_at'], 'audit_logs_branch_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
