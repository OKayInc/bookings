<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointment_types', function (Blueprint $table): void {
            $table->string('attendance_mode', 32)->default('single');
            $table->unsignedInteger('capacity')->default(1);

            $table->string('duration_mode', 32)->default('fixed');
            $table->string('duration_unit', 16)->default('minute');
            $table->unsignedInteger('duration_value')->default(60);
            $table->unsignedInteger('minimum_duration_value')->nullable();
            $table->unsignedInteger('maximum_duration_value')->nullable();
            $table->unsignedInteger('duration_increment_value')->nullable();

            $table->unsignedInteger('buffer_before_minutes')->default(0);
            $table->unsignedInteger('buffer_after_minutes')->default(0);

            $table->string('pricing_mode', 32)->default('free');
            $table->unsignedBigInteger('fixed_price_minor')->nullable();
            $table->unsignedBigInteger('rate_amount_minor')->nullable();
            $table->string('rate_unit', 16)->nullable();

            $table->boolean('requires_resource_confirmation')->default(false);
            $table->string('redirect_url', 2048)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('appointment_types', function (Blueprint $table): void {
            $table->dropColumn([
                'attendance_mode',
                'capacity',
                'duration_mode',
                'duration_unit',
                'duration_value',
                'minimum_duration_value',
                'maximum_duration_value',
                'duration_increment_value',
                'buffer_before_minutes',
                'buffer_after_minutes',
                'pricing_mode',
                'fixed_price_minor',
                'rate_amount_minor',
                'rate_unit',
                'requires_resource_confirmation',
                'redirect_url',
            ]);
        });
    }
};
