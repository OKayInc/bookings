<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointment_types', function (Blueprint $table): void {
            $table->unsignedInteger('booking_notice_value')->default(0)->after('start_interval_minutes');
            $table->string('booking_notice_unit', 16)->default('hour')->after('booking_notice_value');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_types', function (Blueprint $table): void {
            $table->dropColumn(['booking_notice_value', 'booking_notice_unit']);
        });
    }
};
