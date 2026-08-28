<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('calendar_connections', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->binary('resource_id', 16, true);
            $table->string('provider', 24);
            $table->string('external_account_id', 255)->nullable();
            $table->string('external_account_name', 255)->nullable();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->dateTime('token_expires_at_utc', 6)->nullable();
            $table->text('scopes')->nullable();
            $table->string('status', 24)->default('active');
            $table->text('last_error')->nullable();
            $table->dateTime('last_refreshed_at_utc', 6)->nullable();
            $table->timestamps(6);

            $table->unique(['resource_id', 'provider'], 'cc_resource_provider_uq');
            $table->index(['organization_id', 'status'], 'cc_org_status_idx');
            $table->foreign('organization_id', 'cc_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('resource_id', 'cc_resource_fk')->references('id')->on('resources')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_connections');
    }
};
