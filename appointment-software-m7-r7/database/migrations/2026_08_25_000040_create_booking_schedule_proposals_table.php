<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('booking_schedule_proposals', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->binary('booking_id', 16, true);
            $table->binary('booking_hold_id', 16, true)->nullable();
            $table->binary('proposed_by_person_id', 16, true)->nullable();
            $table->binary('original_appointment_id', 16, true);
            $table->string('status', 24)->default('pending');
            $table->binary('client_token_hash', 32, true);
            $table->dateTime('original_starts_at_utc', 6);
            $table->dateTime('original_ends_at_utc', 6);
            $table->dateTime('proposed_starts_at_utc', 6);
            $table->dateTime('proposed_ends_at_utc', 6);
            $table->string('proposed_timezone', 64);
            $table->text('reason')->nullable();
            $table->text('client_message')->nullable();
            $table->boolean('warning_active')->default(false);
            $table->dateTime('expires_at_utc', 6);
            $table->dateTime('responded_at_utc', 6)->nullable();
            $table->timestamps(6);

            $table->index(['booking_id', 'status'], 'bsp_booking_status_idx');
            $table->index(['status', 'expires_at_utc'], 'bsp_status_exp_idx');
            $table->foreign('organization_id', 'bsp_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('booking_id', 'bsp_booking_fk')->references('id')->on('bookings')->cascadeOnDelete();
            $table->foreign('booking_hold_id', 'bsp_hold_fk')->references('id')->on('booking_holds')->nullOnDelete();
            $table->foreign('proposed_by_person_id', 'bsp_person_fk')->references('id')->on('persons')->nullOnDelete();
            $table->foreign('original_appointment_id', 'bsp_orig_appt_fk')->references('id')->on('appointments')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_schedule_proposals');
    }
};
