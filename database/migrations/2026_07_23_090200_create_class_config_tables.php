<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The concrete teaching unit: Class × Shift × Section.
        Schema::create('class_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('sections')->cascadeOnDelete();
            $table->unsignedInteger('serial')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'class_id', 'shift_id', 'section_id'], 'class_config_unique');
            $table->index(['branch_id', 'class_id']);
        });

        // Which groups exist within a class.
        Schema::create('group_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->unsignedInteger('serial')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'class_id', 'group_id'], 'group_config_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_configs');
        Schema::dropIfExists('class_configs');
    }
};
