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
        Schema::create('cms_site_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');

            // Basic Site Information
            $table->string('site_title')->default('Safe Eduman');
            $table->string('site_tagline')->nullable();
            $table->text('site_description')->nullable();

            // Logo and Branding
            $table->foreignId('header_logo_asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->foreignId('footer_logo_asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->foreignId('favicon_asset_id')->nullable()->constrained('assets')->nullOnDelete();

            // Contact Information
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_address')->nullable();

            // Social Media
            $table->string('facebook_url')->nullable();
            $table->string('twitter_url')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('youtube_url')->nullable();
            $table->string('instagram_url')->nullable();

            // SEO and Analytics
            $table->text('google_analytics_code')->nullable();
            $table->text('google_tag_manager_code')->nullable();
            $table->text('meta_keywords')->nullable();

            // Additional Settings
            $table->json('additional_settings')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Ensure one settings record per branch
            $table->unique('branch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_site_settings');
    }
};
