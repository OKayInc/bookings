<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organization_holidays', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->string('preset_key', 64)->nullable();
            $table->string('name', 120);
            $table->string('rule_type', 32);
            $table->unsignedTinyInteger('month')->nullable();
            $table->unsignedTinyInteger('day')->nullable();
            $table->unsignedTinyInteger('weekday')->nullable();
            $table->unsignedTinyInteger('occurrence')->nullable();
            $table->smallInteger('easter_offset_days')->nullable();
            $table->date('specific_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps(6);

            $table->unique(['organization_id', 'preset_key'], 'oh_org_preset_uq');
            $table->index(['organization_id', 'is_active'], 'oh_org_active_idx');
            $table->foreign('organization_id', 'oh_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_holidays');
    }
};
