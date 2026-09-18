<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appointment_question_numeric_constraints', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('appointment_question_id', 16, true);
            $table->binary('source_question_id', 16, true)->nullable();
            $table->string('comparison_operator', 2);
            $table->string('comparison_value', 255)->nullable();
            $table->string('boolean_operator', 8)->default('and');
            $table->unsignedInteger('position')->default(1);
            $table->timestamps(6);

            $table->unique(['appointment_question_id', 'position'], 'aqnc_target_pos_uq');
            $table->foreign('appointment_question_id', 'aqnc_target_fk')->references('id')->on('appointment_questions')->cascadeOnDelete();
            $table->foreign('source_question_id', 'aqnc_source_fk')->references('id')->on('appointment_questions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_question_numeric_constraints');
    }
};
