<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->boolean('collects_taxes')->default(false)->after('tiktok_url');
            $table->string('tax_identifier', 255)->nullable()->after('collects_taxes');
            $table->string('tax_price_mode', 20)->nullable()->after('tax_identifier');
        });

        Schema::create('organization_taxes', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->string('name', 120);
            $table->unsignedInteger('rate_millionths');
            $table->unsignedSmallInteger('position')->default(1);
            $table->timestamps(6);

            $table->unique(['organization_id', 'name'], 'ot_org_name_uq');
            $table->index(['organization_id', 'position'], 'ot_org_position_idx');
            $table->foreign('organization_id', 'ot_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->unsignedBigInteger('subtotal_minor')->default(0)->after('price_minor');
            $table->unsignedBigInteger('tax_total_minor')->default(0)->after('subtotal_minor');
            $table->string('tax_price_mode', 20)->nullable()->after('tax_total_minor');
            $table->string('tax_identifier', 255)->nullable()->after('tax_price_mode');
        });

        DB::table('bookings')->update(['subtotal_minor' => DB::raw('price_minor')]);

        Schema::create('booking_tax_lines', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('booking_id', 16, true);
            $table->string('name', 120);
            $table->unsignedInteger('rate_millionths');
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedSmallInteger('position')->default(1);
            $table->timestamps(6);

            $table->index(['booking_id', 'position'], 'btl_booking_position_idx');
            $table->foreign('booking_id', 'btl_booking_fk')->references('id')->on('bookings')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_tax_lines');

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['subtotal_minor', 'tax_total_minor', 'tax_price_mode', 'tax_identifier']);
        });

        Schema::dropIfExists('organization_taxes');

        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn(['collects_taxes', 'tax_identifier', 'tax_price_mode']);
        });
    }
};
