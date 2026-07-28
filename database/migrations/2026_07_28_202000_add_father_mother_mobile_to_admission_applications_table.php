<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('admission_applications', 'father_mobile')) {
                $table->string('father_mobile')->nullable()->after('father_nid');
            }
            if (! Schema::hasColumn('admission_applications', 'mother_mobile')) {
                $table->string('mother_mobile')->nullable()->after('mother_nid');
            }
        });
    }

    public function down(): void
    {
        Schema::table('admission_applications', function (Blueprint $table) {
            if (Schema::hasColumn('admission_applications', 'father_mobile')) {
                $table->dropColumn('father_mobile');
            }
            if (Schema::hasColumn('admission_applications', 'mother_mobile')) {
                $table->dropColumn('mother_mobile');
            }
        });
    }
};
