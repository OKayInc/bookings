<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointment_types', function (Blueprint $table): void {
            $table->boolean('ticketing_enabled')->default(false)->after('attendance_mode');
            $table->unsignedInteger('show_start_offset_minutes')->nullable()->after('ticketing_enabled');
            $table->unsignedInteger('show_end_offset_minutes')->nullable()->after('show_start_offset_minutes');
            $table->string('ticket_seating_scheme', 32)->default('none')->after('show_end_offset_minutes');
            $table->boolean('ticket_seat_optional')->default(false)->after('ticket_seating_scheme');
            $table->json('ticket_seat_blocks')->nullable()->after('ticket_seat_optional');
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->boolean('ticketing_enabled')->default(false)->after('capacity');
            $table->dateTime('show_starts_at_utc', 6)->nullable()->after('ticketing_enabled');
            $table->dateTime('show_ends_at_utc', 6)->nullable()->after('show_starts_at_utc');
            $table->string('ticket_seating_scheme', 32)->nullable()->after('show_ends_at_utc');
            $table->boolean('ticket_seat_optional')->default(false)->after('ticket_seating_scheme');
            $table->json('ticket_seat_blocks')->nullable()->after('ticket_seat_optional');
        });

        Schema::create('tickets', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->binary('appointment_id', 16, true);
            $table->binary('booking_id', 16, true);
            $table->binary('booking_attendee_id', 16, true);
            $table->string('code', 24)->unique('ticket_code_uq');
            $table->string('status', 24)->default('reserved');
            $table->string('seat_key', 255)->nullable();
            $table->string('section_label', 80)->nullable();
            $table->string('row_label', 80)->nullable();
            $table->string('seat_label', 80)->nullable();
            $table->dateTime('checked_in_at_utc', 6)->nullable();
            $table->binary('checked_in_by_person_id', 16, true)->nullable();
            $table->timestamps(6);

            $table->unique('booking_attendee_id', 'ticket_attendee_uq');
            $table->unique(['appointment_id', 'seat_key'], 'ticket_appointment_seat_uq');
            $table->index(['organization_id', 'status', 'checked_in_at_utc'], 'ticket_org_status_checkin_idx');
            $table->index(['appointment_id', 'status'], 'ticket_appointment_status_idx');
            $table->foreign('organization_id', 'ticket_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('appointment_id', 'ticket_appointment_fk')->references('id')->on('appointments')->cascadeOnDelete();
            $table->foreign('booking_id', 'ticket_booking_fk')->references('id')->on('bookings')->cascadeOnDelete();
            $table->foreign('booking_attendee_id', 'ticket_attendee_fk')->references('id')->on('booking_attendees')->cascadeOnDelete();
            $table->foreign('checked_in_by_person_id', 'ticket_checkin_person_fk')->references('id')->on('persons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');

        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropColumn([
                'ticketing_enabled', 'show_starts_at_utc', 'show_ends_at_utc',
                'ticket_seating_scheme', 'ticket_seat_optional', 'ticket_seat_blocks',
            ]);
        });

        Schema::table('appointment_types', function (Blueprint $table): void {
            $table->dropColumn([
                'ticketing_enabled', 'show_start_offset_minutes', 'show_end_offset_minutes',
                'ticket_seating_scheme', 'ticket_seat_optional', 'ticket_seat_blocks',
            ]);
        });
    }
};
