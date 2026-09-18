<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->string('name', 120);
            $table->string('url', 2048);
            $table->text('secret');
            $table->json('events');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps(6);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });
        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->binary('webhook_endpoint_id', 16, true);
            $table->char('event_id', 36);
            $table->string('event_type', 80);
            $table->unsignedInteger('endpoint_version');
            $table->longText('payload');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('cycle_attempts')->default(0);
            $table->dateTime('available_at', 6);
            $table->dateTime('claimed_at', 6)->nullable();
            $table->char('claim_token', 36)->nullable();
            $table->dateTime('delivered_at', 6)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->timestamps(6);
            $table->unique(['webhook_endpoint_id', 'event_id'], 'wh_delivery_event_uq');
            $table->index(['status', 'available_at'], 'wh_delivery_due_idx');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('webhook_endpoint_id')->references('id')->on('webhook_endpoints')->cascadeOnDelete();
        });
        Schema::create('webhook_attempts', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('webhook_delivery_id', 16, true);
            $table->unsignedInteger('number');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('error', 255)->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamps(6);
            $table->foreign('webhook_delivery_id')->references('id')->on('webhook_deliveries')->cascadeOnDelete();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('webhook_attempts');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
    }
};
