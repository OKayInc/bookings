<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('booking_holds', function (Blueprint $table): void {
            $table->json('ticket_seats')->nullable()->after('attendee_count');
        });

        Schema::table('tickets', function (Blueprint $table): void {
            $table->unsignedBigInteger('seat_fee_minor')->default(0)->after('seat_label');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropColumn('seat_fee_minor');
        });

        Schema::table('booking_holds', function (Blueprint $table): void {
            $table->dropColumn('ticket_seats');
        });
    }
};
