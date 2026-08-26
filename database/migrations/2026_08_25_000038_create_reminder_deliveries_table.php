<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reminder_deliveries', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->binary('booking_id', 16, true)->nullable();
            $table->binary('appointment_id', 16, true);
            $table->binary('resource_id', 16, true)->nullable();
            $table->string('delivery_key', 191);
            $table->string('recipient_kind', 20);
            $table->string('recipient_email', 254);
            $table->dateTime('sent_at_utc', 6);
            $table->timestamps(6);

            $table->unique('delivery_key', 'rd_key_uq');
            $table->index(['appointment_id', 'recipient_kind'], 'rd_appt_kind_idx');
            $table->foreign('organization_id', 'rd_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('booking_id', 'rd_booking_fk')->references('id')->on('bookings')->cascadeOnDelete();
            $table->foreign('appointment_id', 'rd_appt_fk')->references('id')->on('appointments')->cascadeOnDelete();
            $table->foreign('resource_id', 'rd_resource_fk')->references('id')->on('resources')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_deliveries');
    }
};
