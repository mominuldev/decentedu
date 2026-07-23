<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Polymorphic-by-type content table backing the whole public site — mirrors the legacy
        // design (one `posts` table, `type` discriminator drives page/news/notice/slider/etc).
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->enum('type', [
                'page', 'news', 'notice', 'slider', 'teacher', 'staff', 'committee',
                'gallery', 'result', 'homepage_person', 'instruction',
            ]);
            $table->string('title');
            $table->string('slug');
            $table->longText('body')->nullable();
            $table->text('description')->nullable();
            $table->string('keywords')->nullable();
            $table->string('image_path')->nullable();
            $table->json('meta')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'type', 'slug'], 'post_slug_unique');
            $table->index(['branch_id', 'type', 'status'], 'post_type_status_index');
        });

        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('location', ['header', 'footer'])->default('header');
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'name'], 'menu_name_unique');
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->string('label');
            $table->string('url')->nullable();
            $table->foreignId('post_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->nullOnDelete();
            $table->unsignedInteger('serial')->default(0);
            $table->enum('target', ['_self', '_blank'])->default('_self');
            $table->timestamps();

            $table->index(['menu_id', 'parent_id'], 'menu_item_menu_parent_index');
        });

        // Singleton per branch (one row, upserted) — basic site info shown in the public header/
        // footer/meta tags.
        Schema::create('website_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('site_title')->nullable();
            $table->string('tagline')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->json('social_links')->nullable();
            $table->text('meta_description')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_settings');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
        Schema::dropIfExists('posts');
    }
};
