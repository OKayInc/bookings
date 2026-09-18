<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_types', function (Blueprint $table): void {
            // Preserve M4-R3's existing global 365-day booking horizon for
            // already-created appointment types. Setting the value to zero
            // explicitly disables the maximum horizon.
            $table->unsignedInteger('maximum_booking_notice_value')->default(365)->after('booking_notice_unit');
            $table->string('maximum_booking_notice_unit', 16)->default('day')->after('maximum_booking_notice_value');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_types', function (Blueprint $table): void {
            $table->dropColumn(['maximum_booking_notice_value', 'maximum_booking_notice_unit']);
        });
    }
};
