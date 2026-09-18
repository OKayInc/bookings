<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appointment_questions', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('appointment_type_id', 16, true);
            $table->string('type', 32);
            $table->string('label', 255);
            $table->text('description')->nullable();
            $table->string('placeholder', 255)->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(1);
            $table->json('configuration')->nullable();
            $table->string('pricing_adjustment_type', 24)->default('none');
            $table->string('pricing_application_mode', 24)->default('once');
            $table->unsignedBigInteger('pricing_amount_minor')->nullable();
            $table->unsignedInteger('pricing_percentage_bps')->nullable();
            $table->string('pricing_percentage_basis', 24)->default('base_price');
            $table->unsignedInteger('pricing_included_units')->default(0);
            $table->timestamps(6);

            $table->index(['appointment_type_id', 'is_active', 'position'], 'aq_type_active_pos_idx');
            $table->foreign('appointment_type_id', 'aq_type_fk')->references('id')->on('appointment_types')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_questions');
    }
};
