<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('organization_payment_settings', function (Blueprint $table): void {
            $table->boolean('offline_payment_enabled')->default(false)->after('default_provider');
            $table->unsignedInteger('offline_payment_window_minutes')->default(1440)->after('offline_payment_enabled');
            $table->text('offline_payment_instructions')->nullable()->after('offline_payment_window_minutes');
        });

        Schema::table('appointment_types', function (Blueprint $table): void {
            $table->boolean('offline_payment_use_organization_defaults')->default(true)
                ->after('offline_payment_instructions');
        });

        // Preserve every existing appointment type exactly as configured before this migration.
        DB::table('appointment_types')->update(['offline_payment_use_organization_defaults' => false]);
    }

    public function down(): void
    {
        Schema::table('appointment_types', function (Blueprint $table): void {
            $table->dropColumn('offline_payment_use_organization_defaults');
        });

        Schema::table('organization_payment_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'offline_payment_enabled',
                'offline_payment_window_minutes',
                'offline_payment_instructions',
            ]);
        });
    }
};
