<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('external_calendars', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('calendar_connection_id', 16, true);
            $table->text('external_id');
            $table->binary('external_id_hash', 32, true);
            $table->string('name', 255);
            $table->string('timezone', 64)->nullable();
            $table->string('access_role', 64)->nullable();
            $table->boolean('can_write')->default(false);
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->dateTime('last_seen_at_utc', 6)->nullable();
            $table->timestamps(6);

            $table->unique(['calendar_connection_id', 'external_id_hash'], 'ec_conn_ext_uq');
            $table->index(['calendar_connection_id', 'is_active'], 'ec_conn_active_idx');
            $table->foreign('calendar_connection_id', 'ec_conn_fk')->references('id')->on('calendar_connections')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_calendars');
    }
};
