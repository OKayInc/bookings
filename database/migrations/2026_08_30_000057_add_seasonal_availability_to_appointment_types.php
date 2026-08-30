<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointment_types', function (Blueprint $table): void {
            $table->boolean('seasonal_availability_enabled')->default(false)->after('maximum_booking_notice_unit');
            $table->date('season_start_date')->nullable()->after('seasonal_availability_enabled');
            $table->date('season_end_date')->nullable()->after('season_start_date');
            $table->string('season_recurrence', 16)->nullable()->after('season_end_date');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_types', function (Blueprint $table): void {
            $table->dropColumn([
                'seasonal_availability_enabled',
                'season_start_date',
                'season_end_date',
                'season_recurrence',
            ]);
        });
    }
};
