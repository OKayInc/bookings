<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('short_notice_fee_rules', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('appointment_type_id', 16, true);
            $table->unsignedInteger('threshold_value');
            $table->string('threshold_unit', 16);
            $table->string('adjustment_type', 24);
            $table->unsignedBigInteger('fixed_amount_minor')->nullable();
            $table->unsignedInteger('percentage_bps')->nullable();
            $table->unsignedInteger('position')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps(6);

            $table->unique(
                ['appointment_type_id', 'threshold_value', 'threshold_unit'],
                'snfr_type_threshold_uq',
            );
            $table->index(
                ['appointment_type_id', 'is_active', 'position'],
                'snfr_type_active_pos_idx',
            );
            $table->foreign('appointment_type_id', 'snfr_type_fk')
                ->references('id')
                ->on('appointment_types')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('short_notice_fee_rules');
    }
};
