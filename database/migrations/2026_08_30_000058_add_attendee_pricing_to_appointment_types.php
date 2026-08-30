<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointment_types', function (Blueprint $table): void {
            $table->unsignedBigInteger('attendee_price_minor')->nullable()->after('fixed_price_minor');
            $table->string('attendee_pricing_mode', 16)->default('flat')->after('attendee_price_minor');
            $table->json('attendee_price_ranges')->nullable()->after('attendee_pricing_mode');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_types', function (Blueprint $table): void {
            $table->dropColumn(['attendee_price_minor', 'attendee_pricing_mode', 'attendee_price_ranges']);
        });
    }
};
