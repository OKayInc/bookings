<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appointment_question_visibility_conditions', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('appointment_question_id', 16, true);
            $table->binary('source_question_id', 16, true);
            $table->binary('question_option_id', 16, true);
            $table->string('boolean_operator', 8)->default('and');
            $table->unsignedInteger('position')->default(1);
            $table->timestamps(6);

            $table->unique(['appointment_question_id', 'position'], 'aqvc_target_pos_uq');
            $table->index(['source_question_id', 'question_option_id'], 'aqvc_source_option_idx');
            $table->foreign('appointment_question_id', 'aqvc_target_fk')->references('id')->on('appointment_questions')->cascadeOnDelete();
            $table->foreign('source_question_id', 'aqvc_source_fk')->references('id')->on('appointment_questions')->cascadeOnDelete();
            $table->foreign('question_option_id', 'aqvc_option_fk')->references('id')->on('question_options')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_question_visibility_conditions');
    }
};
