<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('booking_holds', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->binary('appointment_type_id', 16, true);
            $table->binary('token_hash', 32, true)->unique('bh_token_uq');
            $table->dateTime('starts_at_utc', 6);
            $table->dateTime('ends_at_utc', 6);
            $table->dateTime('blocked_starts_at_utc', 6);
            $table->dateTime('blocked_ends_at_utc', 6);
            $table->string('booking_timezone', 64);
            $table->unsignedInteger('duration_value');
            $table->string('status', 24)->default('active');
            $table->dateTime('expires_at_utc', 6);
            $table->timestamps(6);

            $table->index(['appointment_type_id', 'status', 'expires_at_utc'], 'bh_type_status_exp_idx');
            $table->index(['organization_id', 'status', 'expires_at_utc'], 'bh_org_status_exp_idx');
            $table->index(['blocked_starts_at_utc', 'blocked_ends_at_utc'], 'bh_blocked_range_idx');
            $table->foreign('organization_id', 'bh_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('appointment_type_id', 'bh_type_fk')->references('id')->on('appointment_types')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_holds');
    }
};
