<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notices and Events — lightweight "list content" for the public site (docs/08 §CMS). Unlike
 * pages/posts these carry no blocks or SEO; a notice is a dated announcement with an optional
 * downloadable file (PDF/Excel), an event is a dated happening with an optional cover image.
 * Both categorise through the shared taxonomy/terms (`termables`) and are branch-scoped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->longText('body')->nullable();
            $table->date('notice_date');
            $table->foreignId('attachment_asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->boolean('is_important')->default(false)->index();
            $table->string('status')->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'slug']);
            $table->index(['branch_id', 'status', 'notice_date']);
        });

        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->longText('body')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('location')->nullable();
            $table->foreignId('featured_asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->string('status')->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'slug']);
            $table->index(['branch_id', 'status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
        Schema::dropIfExists('notices');
    }
};
