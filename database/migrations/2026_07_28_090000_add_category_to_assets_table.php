<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets the shared CMS media library also back non-CMS uploads (Student/Employee photos,
 * Branch logos) that need to stay off the public disk. 'cms' assets keep serving from the
 * public disk via Media::getUrl(); 'photo'/'logo' assets are stored on the private 'local'
 * disk and served through the branch-authenticated AssetController::serve() route.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->string('category')->default('cms')->after('media_folder_id');
            $table->index(['branch_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->dropIndex(['branch_id', 'category']);
            $table->dropColumn('category');
        });
    }
};
