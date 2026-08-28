<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('availability_schedules', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->string('scope_type', 32);
            $table->binary('scope_id', 16, true);
            $table->string('timezone', 64);
            $table->boolean('is_active')->default(true);
            $table->timestamps(6);

            $table->unique(['organization_id', 'scope_type', 'scope_id'], 'av_sched_scope_uq');
            $table->index(['organization_id', 'scope_type'], 'av_sched_org_scope_idx');
            $table->foreign('organization_id', 'av_sched_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_schedules');
    }
};
