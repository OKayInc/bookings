<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->binary('appointment_type_id', 16, true);
            $table->dateTime('starts_at_utc', 6);
            $table->dateTime('ends_at_utc', 6);
            $table->dateTime('blocked_starts_at_utc', 6);
            $table->dateTime('blocked_ends_at_utc', 6);
            $table->string('scheduling_timezone', 64);
            $table->unsignedInteger('duration_value');
            $table->unsignedInteger('capacity')->default(1);
            $table->string('status', 24)->default('scheduled');
            $table->timestamps(6);

            $table->index(['appointment_type_id', 'starts_at_utc', 'status'], 'appt_type_start_status_idx');
            $table->index(['organization_id', 'starts_at_utc', 'status'], 'appt_org_start_status_idx');
            $table->index(['blocked_starts_at_utc', 'blocked_ends_at_utc'], 'appt_blocked_range_idx');
            $table->foreign('organization_id', 'appt_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('appointment_type_id', 'appt_type_fk')->references('id')->on('appointment_types')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
