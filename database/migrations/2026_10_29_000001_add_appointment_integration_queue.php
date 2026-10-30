<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appointment_sync_requests', function (Blueprint $table): void {
            $table->binary('appointment_id', 16, true)->primary();
            $table->uuid('generation');
            $table->boolean('force')->default(false);
            $table->dateTime('requested_at_utc', 6);
            $table->dateTime('available_at_utc', 6)->index('asr_available_idx');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->foreign('appointment_id', 'asr_appointment_fk')->references('id')->on('appointments')->cascadeOnDelete();
        });
        Schema::table('appointment_external_events', function (Blueprint $table): void {
            $table->char('payload_hash', 64)->nullable();
        });
        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table): void {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_sync_requests');
        Schema::table('appointment_external_events', fn (Blueprint $table) => $table->dropColumn('payload_hash'));
        // Retain failed_jobs: an existing installation may already own this table.
    }
};
