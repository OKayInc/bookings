<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('booking_reschedules', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('booking_id', 16, true);
            $table->binary('from_appointment_id', 16, true);
            $table->binary('to_appointment_id', 16, true);
            $table->binary('performed_by_person_id', 16, true)->nullable();
            $table->boolean('client_initiated')->default(true);
            $table->dateTime('from_starts_at_utc', 6);
            $table->dateTime('from_ends_at_utc', 6);
            $table->dateTime('to_starts_at_utc', 6);
            $table->dateTime('to_ends_at_utc', 6);
            $table->text('reason')->nullable();
            $table->timestamps(6);

            $table->index(['booking_id', 'created_at'], 'br_booking_created_idx');
            $table->foreign('booking_id', 'br_booking_fk')->references('id')->on('bookings')->cascadeOnDelete();
            $table->foreign('from_appointment_id', 'br_from_appt_fk')->references('id')->on('appointments')->restrictOnDelete();
            $table->foreign('to_appointment_id', 'br_to_appt_fk')->references('id')->on('appointments')->restrictOnDelete();
            $table->foreign('performed_by_person_id', 'br_person_fk')->references('id')->on('persons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_reschedules');
    }
};
