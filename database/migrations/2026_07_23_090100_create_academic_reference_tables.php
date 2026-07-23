<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Reference tables sharing the uniform name / name_bn / serial / status shape. */
    private array $tables = ['classes', 'shifts', 'sections', 'groups', 'categories', 'subjects'];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->id();
                $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('name_bn')->nullable();
                if ($name === 'subjects') {
                    $table->string('code')->nullable();
                }
                $table->unsignedInteger('serial')->default(0);
                $table->boolean('status')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['branch_id', 'name']);
                $table->index(['branch_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $name) {
            Schema::dropIfExists($name);
        }
    }
};
