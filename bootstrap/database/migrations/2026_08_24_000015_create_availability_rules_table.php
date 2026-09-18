<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('availability_rules', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('schedule_id', 16, true);
            $table->unsignedTinyInteger('weekday');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps(6);

            $table->index(['schedule_id', 'weekday', 'start_time'], 'av_rule_sched_day_idx');
            $table->foreign('schedule_id', 'av_rule_sched_fk')->references('id')->on('availability_schedules')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_rules');
    }
};
