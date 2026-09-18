<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('calendar_oauth_states', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('user_id', 16, true);
            $table->binary('organization_id', 16, true);
            $table->binary('resource_id', 16, true);
            $table->string('provider', 24);
            $table->char('state_hash', 64);
            $table->dateTime('expires_at_utc', 6);
            $table->dateTime('consumed_at_utc', 6)->nullable();
            $table->timestamps(6);

            $table->unique('state_hash', 'cos_state_hash_uq');
            $table->index(['user_id', 'expires_at_utc'], 'cos_user_exp_idx');
            $table->index(['resource_id', 'provider', 'consumed_at_utc'], 'cos_resource_provider_idx');
            $table->foreign('user_id', 'cos_user_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('organization_id', 'cos_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('resource_id', 'cos_resource_fk')->references('id')->on('resources')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_oauth_states');
    }
};
