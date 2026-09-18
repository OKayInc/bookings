<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('holiday_region', 16)->nullable()->after('timezone');
        });

        Schema::table('organization_holidays', function (Blueprint $table): void {
            $table->string('region_code', 16)->nullable()->after('preset_key');
            $table->string('provider_holiday_key', 96)->nullable()->after('region_code');
            $table->index(
                ['organization_id', 'region_code', 'provider_holiday_key'],
                'oh_org_region_key_idx',
            );
        });

        Schema::table('organization_resources', function (Blueprint $table): void {
            $table->boolean('enforce_holidays')->default(false)->after('is_required_by_default');
            $table->string('holiday_region', 16)->nullable()->after('enforce_holidays');
            $table->index(
                ['organization_id', 'enforce_holidays'],
                'org_res_holiday_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('organization_resources', function (Blueprint $table): void {
            $table->dropIndex('org_res_holiday_idx');
            $table->dropColumn(['enforce_holidays', 'holiday_region']);
        });

        Schema::table('organization_holidays', function (Blueprint $table): void {
            $table->dropIndex('oh_org_region_key_idx');
            $table->dropColumn(['region_code', 'provider_holiday_key']);
        });

        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn('holiday_region');
        });
    }
};
