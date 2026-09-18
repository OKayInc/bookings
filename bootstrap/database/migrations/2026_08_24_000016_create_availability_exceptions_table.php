<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('availability_exceptions', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('schedule_id', 16, true);
            $table->dateTime('starts_at_utc', 6);
            $table->dateTime('ends_at_utc', 6);
            $table->string('mode', 24);
            $table->string('timezone', 64);
            $table->string('reason', 255)->nullable();
            $table->timestamps(6);

            $table->index(['schedule_id', 'starts_at_utc', 'ends_at_utc'], 'av_exc_sched_range_idx');
            $table->foreign('schedule_id', 'av_exc_sched_fk')->references('id')->on('availability_schedules')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_exceptions');
    }
};
