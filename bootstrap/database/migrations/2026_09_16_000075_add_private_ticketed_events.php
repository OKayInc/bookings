<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointment_types', function (Blueprint $table): void {
            $table->boolean('private_event_enabled')->default(false)->after('ticketing_enabled');
            $table->text('event_location')->nullable()->after('private_event_enabled');
            $table->string('location_disclosure_mode', 32)->default('public')->after('event_location');
            $table->unsignedInteger('location_disclosure_hours')->nullable()->after('location_disclosure_mode');
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->boolean('private_event_enabled')->default(false)->after('ticketing_enabled');
            $table->text('event_location')->nullable()->after('private_event_enabled');
            $table->string('location_disclosure_mode', 32)->default('public')->after('event_location');
            $table->unsignedInteger('location_disclosure_hours')->nullable()->after('location_disclosure_mode');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->boolean('requires_event_approval')->default(false)->after('requires_resource_confirmation');
            $table->dateTime('location_notification_sent_at_utc', 6)->nullable()->after('requires_event_approval');
        });

        Schema::create('event_admission_approvals', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->binary('booking_id', 16, true);
            $table->binary('coordinator_person_id', 16, true);
            $table->binary('responded_by_person_id', 16, true)->nullable();
            $table->string('recipient_email', 254);
            $table->string('status', 24)->default('pending');
            $table->binary('response_token_hash', 32);
            $table->text('response_note')->nullable();
            $table->dateTime('notification_sent_at_utc', 6)->nullable();
            $table->dateTime('responded_at_utc', 6)->nullable();
            $table->timestamps(6);

            $table->unique(['booking_id', 'coordinator_person_id'], 'event_approval_booking_person_uq');
            $table->index(['booking_id', 'status'], 'event_approval_booking_status_idx');
            $table->foreign('organization_id', 'event_approval_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('booking_id', 'event_approval_booking_fk')->references('id')->on('bookings')->cascadeOnDelete();
            $table->foreign('coordinator_person_id', 'event_approval_coordinator_fk')->references('id')->on('persons')->cascadeOnDelete();
            $table->foreign('responded_by_person_id', 'event_approval_responder_fk')->references('id')->on('persons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_admission_approvals');
        Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn(['requires_event_approval', 'location_notification_sent_at_utc']));
        Schema::table('appointments', fn (Blueprint $table) => $table->dropColumn(['private_event_enabled', 'event_location', 'location_disclosure_mode', 'location_disclosure_hours']));
        Schema::table('appointment_types', fn (Blueprint $table) => $table->dropColumn(['private_event_enabled', 'event_location', 'location_disclosure_mode', 'location_disclosure_hours']));
    }
};
