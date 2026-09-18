<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('resources', function (Blueprint $table): void {
            $table->unsignedInteger('inventory_quantity')->default(1)->after('type');
            $table->index(['type', 'is_active'], 'resources_type_active_idx');
        });

        Schema::table('appointment_type_resources', function (Blueprint $table): void {
            $table->unsignedInteger('quantity_required')->default(1)->after('replacement_group');
            $table->string('equipment_pricing_mode', 24)->default('free')->after('quantity_required');
            $table->unsignedBigInteger('equipment_unit_price_minor')->nullable()->after('equipment_pricing_mode');
            $table->unsignedBigInteger('equipment_fixed_price_minor')->nullable()->after('equipment_unit_price_minor');
            $table->json('equipment_bundle_prices')->nullable()->after('equipment_fixed_price_minor');
        });

        Schema::table('booking_hold_resources', function (Blueprint $table): void {
            $table->unsignedInteger('quantity_reserved')->default(1)->after('replacement_group');
        });

        Schema::table('appointment_resources', function (Blueprint $table): void {
            $table->unsignedInteger('quantity_reserved')->default(1)->after('replacement_group');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_resources', function (Blueprint $table): void {
            $table->dropColumn('quantity_reserved');
        });

        Schema::table('booking_hold_resources', function (Blueprint $table): void {
            $table->dropColumn('quantity_reserved');
        });

        Schema::table('appointment_type_resources', function (Blueprint $table): void {
            $table->dropColumn([
                'quantity_required',
                'equipment_pricing_mode',
                'equipment_unit_price_minor',
                'equipment_fixed_price_minor',
                'equipment_bundle_prices',
            ]);
        });

        Schema::table('resources', function (Blueprint $table): void {
            $table->dropIndex('resources_type_active_idx');
            $table->dropColumn('inventory_quantity');
        });
    }
};
