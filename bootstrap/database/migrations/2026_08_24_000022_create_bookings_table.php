<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->binary('appointment_id', 16, true);
            $table->binary('appointment_type_id', 16, true);
            $table->binary('organization_contact_id', 16, true);
            $table->binary('appointment_type_invitation_id', 16, true)->nullable();
            $table->binary('contract_template_id', 16, true)->nullable();

            $table->char('reference', 12)->unique('booking_reference_uq');
            $table->string('status', 40);
            $table->unsignedInteger('attendee_count')->default(1);
            $table->string('booking_timezone', 64);
            $table->unsignedBigInteger('price_minor')->default(0);
            $table->char('currency', 3);

            $table->string('first_name', 120);
            $table->string('last_name', 120);
            $table->string('email', 254);
            $table->string('email_normalized', 254);
            $table->string('phone', 64)->nullable();

            $table->dateTime('email_verified_at', 6)->nullable();
            $table->binary('email_verification_token_hash', 32, true)->nullable();
            $table->dateTime('email_verification_expires_at_utc', 6)->nullable();
            $table->binary('manage_token_hash', 32, true);
            $table->dateTime('expires_at_utc', 6)->nullable();
            $table->timestamps(6);

            $table->index(['organization_id', 'created_at'], 'booking_org_created_idx');
            $table->index(['appointment_id', 'status'], 'booking_appt_status_idx');
            $table->index(['organization_contact_id', 'created_at'], 'booking_contact_created_idx');
            $table->index(['email_normalized', 'created_at'], 'booking_email_created_idx');
            $table->foreign('organization_id', 'booking_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('appointment_id', 'booking_appt_fk')->references('id')->on('appointments')->cascadeOnDelete();
            $table->foreign('appointment_type_id', 'booking_type_fk')->references('id')->on('appointment_types')->cascadeOnDelete();
            $table->foreign('organization_contact_id', 'booking_contact_fk')->references('id')->on('organization_contacts')->restrictOnDelete();
            $table->foreign('appointment_type_invitation_id', 'booking_invite_fk')->references('id')->on('appointment_type_invitations')->nullOnDelete();
            $table->foreign('contract_template_id', 'booking_contract_fk')->references('id')->on('appointment_contract_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
