<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CMS core schema (ported from the starter-cms blueprint, adapted to decentedu's row-level
 * multi-branch tenancy). Aggregate roots carry a `branch_id`; blocks, terms, and SEO rows inherit
 * their owner's branch. Replaces the earlier legacy-shaped single-`posts`-table CMS.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- Media library --------------------------------------------------------------
        Schema::create('media_folders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('media_folders')->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['branch_id', 'parent_id', 'position']);
        });

        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('alt_text')->nullable();
            $table->string('caption')->nullable();
            $table->foreignId('media_folder_id')->nullable()->constrained('media_folders')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'name']);
        });

        // ---- SEO (polymorphic) ----------------------------------------------------------
        Schema::create('seo', function (Blueprint $table): void {
            $table->id();
            $table->morphs('seoable');
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('robots')->nullable();
            $table->string('og_title')->nullable();
            $table->string('og_description', 500)->nullable();
            $table->foreignId('og_image_asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->json('structured_data')->nullable();
            $table->timestamps();

            $table->unique(['seoable_type', 'seoable_id']);
        });

        // ---- Pages (hierarchical, path-routed) ------------------------------------------
        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->string('path');
            $table->foreignId('parent_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->string('template')->default('default');
            $table->text('excerpt')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('featured_asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'path']);
            $table->unique(['branch_id', 'parent_id', 'slug']);
            $table->index(['branch_id', 'status', 'published_at']);
        });

        // ---- Posts (blog) ---------------------------------------------------------------
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_featured')->default(false)->index();
            $table->unsignedInteger('reading_time')->nullable();
            $table->foreignId('featured_asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'slug']);
            $table->index(['branch_id', 'status', 'published_at']);
        });

        // ---- Blocks (polymorphic, ordered, page-wise) -----------------------------------
        Schema::create('blocks', function (Blueprint $table): void {
            $table->id();
            $table->morphs('blockable');
            $table->string('type')->index();
            $table->json('payload');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index(['blockable_type', 'blockable_id', 'position']);
        });

        // ---- Taxonomies & terms ---------------------------------------------------------
        Schema::create('taxonomies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->boolean('hierarchical')->default(true);
            // Content types this taxonomy applies to (e.g. ["post","event","notice"]); null = all.
            $table->json('object_types')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'slug']);
        });

        Schema::create('terms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('taxonomy_id')->constrained('taxonomies')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->foreignId('parent_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('featured_asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->timestamps();

            $table->unique(['taxonomy_id', 'slug']);
            $table->index(['taxonomy_id', 'parent_id']);
        });

        Schema::create('termables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
            $table->morphs('termable');
            $table->timestamps();

            $table->unique(['term_id', 'termable_type', 'termable_id']);
        });

        // ---- Menus ----------------------------------------------------------------------
        Schema::create('menus', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('key');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'key']);
        });

        Schema::create('menu_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->string('label');
            $table->nullableMorphs('linkable');
            $table->string('url', 2048)->nullable();
            $table->string('target', 20)->default('_self');
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['menu_id', 'parent_id', 'position']);
        });

        // ---- Redirects ------------------------------------------------------------------
        Schema::create('redirects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('from_path');
            $table->string('to_path');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->unsignedBigInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'from_path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
        Schema::dropIfExists('termables');
        Schema::dropIfExists('terms');
        Schema::dropIfExists('taxonomies');
        Schema::dropIfExists('blocks');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('seo');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('media_folders');
    }
};
