<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appointment_question_visibility_condition_options', function (Blueprint $table): void {
            $table->binary('visibility_condition_id', 16, true);
            $table->binary('question_option_id', 16, true);

            $table->primary(['visibility_condition_id', 'question_option_id'], 'aqvco_pk');
            $table->index('question_option_id', 'aqvco_option_idx');
            $table->foreign('visibility_condition_id', 'aqvco_condition_fk')
                ->references('id')
                ->on('appointment_question_visibility_conditions')
                ->cascadeOnDelete();
            $table->foreign('question_option_id', 'aqvco_option_fk')
                ->references('id')
                ->on('question_options')
                ->cascadeOnDelete();
        });

        DB::table('appointment_question_visibility_condition_options')->insertUsing(
            ['visibility_condition_id', 'question_option_id'],
            DB::table('appointment_question_visibility_conditions')->select(['id', 'question_option_id']),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_question_visibility_condition_options');
    }
};
