<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('resource_confirmations', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->binary('booking_id', 16, true);
            $table->binary('appointment_id', 16, true);
            $table->binary('resource_id', 16, true);
            $table->binary('person_id', 16, true)->nullable();
            $table->binary('responded_by_person_id', 16, true)->nullable();
            $table->boolean('is_required')->default(true);
            $table->string('status', 20)->default('pending');
            $table->binary('response_token_hash', 32, true);
            $table->text('response_note')->nullable();
            $table->dateTime('notification_sent_at_utc', 6)->nullable();
            $table->dateTime('responded_at_utc', 6)->nullable();
            $table->timestamps(6);

            $table->unique(['booking_id', 'resource_id'], 'rc_booking_resource_uq');
            $table->index(['booking_id', 'status'], 'rc_booking_status_idx');
            $table->index(['person_id', 'status'], 'rc_person_status_idx');
            $table->foreign('organization_id', 'rc_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('booking_id', 'rc_booking_fk')->references('id')->on('bookings')->cascadeOnDelete();
            $table->foreign('appointment_id', 'rc_appt_fk')->references('id')->on('appointments')->cascadeOnDelete();
            $table->foreign('resource_id', 'rc_resource_fk')->references('id')->on('resources')->cascadeOnDelete();
            $table->foreign('person_id', 'rc_person_fk')->references('id')->on('persons')->nullOnDelete();
            $table->foreign('responded_by_person_id', 'rc_responder_fk')->references('id')->on('persons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_confirmations');
    }
};
