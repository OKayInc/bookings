<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appointment_question_resource_rules', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('appointment_question_id', 16, true);
            $table->binary('trigger_option_id', 16, true);
            $table->binary('unavailable_default_option_id', 16, true);
            $table->string('group_name', 80);
            $table->string('fulfillment_mode', 16);
            $table->timestamps(6);

            $table->unique('appointment_question_id', 'aqrr_question_uq');
            $table->foreign('appointment_question_id', 'aqrr_question_fk')
                ->references('id')->on('appointment_questions')->cascadeOnDelete();
            $table->foreign('trigger_option_id', 'aqrr_trigger_option_fk')
                ->references('id')->on('question_options')->cascadeOnDelete();
            $table->foreign('unavailable_default_option_id', 'aqrr_default_option_fk')
                ->references('id')->on('question_options')->cascadeOnDelete();
        });

        Schema::create('appointment_question_resource_rule_resources', function (Blueprint $table): void {
            $table->binary('resource_rule_id', 16, true);
            $table->binary('resource_id', 16, true);

            $table->primary(['resource_rule_id', 'resource_id'], 'aqrrr_primary');
            $table->foreign('resource_rule_id', 'aqrrr_rule_fk')
                ->references('id')->on('appointment_question_resource_rules')->cascadeOnDelete();
            $table->foreign('resource_id', 'aqrrr_resource_fk')
                ->references('id')->on('resources')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_question_resource_rule_resources');
        Schema::dropIfExists('appointment_question_resource_rules');
    }
};
