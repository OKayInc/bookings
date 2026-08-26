<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('question_options', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('appointment_question_id', 16, true);
            $table->string('label', 255);
            $table->string('value', 180);
            $table->unsignedInteger('position')->default(1);
            $table->boolean('is_active')->default(true);
            $table->string('pricing_adjustment_type', 24)->default('none');
            $table->unsignedBigInteger('pricing_amount_minor')->nullable();
            $table->unsignedInteger('pricing_percentage_bps')->nullable();
            $table->string('pricing_percentage_basis', 24)->default('base_price');
            $table->timestamps(6);

            $table->unique(['appointment_question_id', 'value'], 'qo_question_value_uq');
            $table->index(['appointment_question_id', 'is_active', 'position'], 'qo_question_active_pos_idx');
            $table->foreign('appointment_question_id', 'qo_question_fk')->references('id')->on('appointment_questions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_options');
    }
};
