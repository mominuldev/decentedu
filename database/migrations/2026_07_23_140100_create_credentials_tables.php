<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // transfer_certificates / testimonials / certificates already exist (Phase 4).
        // This is the one new Credentials table: field-toggle config for ID card layout,
        // no visual builder — `fields` just lists which of a fixed set to render.
        Schema::create('id_card_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('holder_type', ['student', 'employee']);
            $table->json('fields');
            $table->boolean('show_qr')->default(false);
            $table->string('primary_color')->nullable();
            $table->string('logo_path')->nullable();
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'name'], 'id_card_template_name_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('id_card_templates');
    }
};
