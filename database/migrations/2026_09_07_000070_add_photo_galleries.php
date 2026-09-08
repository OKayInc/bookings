<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('plan_tier', 16)->default('free')->after('currency');
            $table->index('plan_tier', 'organizations_plan_tier_idx');
        });

        Schema::create('gallery_photos', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->binary('appointment_type_id', 16, true)->nullable();
            $table->string('placement', 16)->default('above');
            $table->unsignedInteger('position')->default(1);
            $table->string('disk', 32);
            $table->string('path', 500);
            $table->string('original_name')->nullable();
            $table->string('alt_text')->nullable();
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedBigInteger('file_size');
            $table->char('sha256', 64);
            $table->timestamps(6);

            $table->index(
                ['organization_id', 'appointment_type_id', 'placement', 'position'],
                'gallery_owner_place_position_idx'
            );
            $table->unique(['disk', 'path'], 'gallery_disk_path_uq');
            $table->foreign('organization_id', 'gallery_organization_fk')
                ->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('appointment_type_id', 'gallery_appointment_type_fk')
                ->references('id')->on('appointment_types')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_photos');

        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropIndex('organizations_plan_tier_idx');
            $table->dropColumn('plan_tier');
        });
    }
};
