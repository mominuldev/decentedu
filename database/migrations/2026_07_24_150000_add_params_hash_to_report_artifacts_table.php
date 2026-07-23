<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_artifacts', function (Blueprint $table) {
            $table->string('params_hash', 32)->nullable()->after('params');
            $table->index(['branch_id', 'report_key', 'format', 'params_hash'], 'report_artifact_dedup_index');
        });
    }

    public function down(): void
    {
        Schema::table('report_artifacts', function (Blueprint $table) {
            $table->dropIndex('report_artifact_dedup_index');
            $table->dropColumn('params_hash');
        });
    }
};
