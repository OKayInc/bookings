<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointment_question_numeric_constraints', function (Blueprint $table): void {
            // NULL preserves M7-R20's source-question/fixed-value inference.
            $table->string('operand_type', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('appointment_question_numeric_constraints', function (Blueprint $table): void {
            $table->dropColumn('operand_type');
        });
    }
};
