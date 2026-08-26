<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appointment_external_events', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('appointment_id', 16, true);
            $table->binary('external_calendar_id', 16, true);
            $table->text('provider_event_id');
            $table->string('etag', 255)->nullable();
            $table->string('sync_status', 24)->default('synced');
            $table->text('last_error')->nullable();
            $table->dateTime('last_synced_at_utc', 6)->nullable();
            $table->timestamps(6);

            $table->unique(['appointment_id', 'external_calendar_id'], 'aee_appt_cal_uq');
            $table->index(['sync_status', 'last_synced_at_utc'], 'aee_status_sync_idx');
            $table->foreign('appointment_id', 'aee_appt_fk')->references('id')->on('appointments')->cascadeOnDelete();
            $table->foreign('external_calendar_id', 'aee_calendar_fk')->references('id')->on('external_calendars')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_external_events');
    }
};
