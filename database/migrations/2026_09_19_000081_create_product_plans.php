<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->boolean('hide_platform_branding')->default(false)->after('plan_tier');
        });

        Schema::create('organization_plan_subscriptions', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true)->unique('ops_org_uq');
            $table->string('provider', 24)->default('stripe');
            $table->string('provider_customer_id', 191)->nullable()->unique('ops_customer_uq');
            $table->string('provider_subscription_id', 191)->nullable()->unique('ops_subscription_uq');
            $table->string('checkout_session_id', 191)->nullable()->unique('ops_checkout_uq');
            $table->string('status', 24)->default('incomplete');
            $table->string('billing_interval', 12)->nullable();
            $table->dateTime('trial_ends_at_utc', 6)->nullable();
            $table->dateTime('current_period_ends_at_utc', 6)->nullable();
            $table->dateTime('grace_ends_at_utc', 6)->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamps(6);
            $table->foreign('organization_id', 'plsub_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
            $table->index(['status', 'grace_ends_at_utc'], 'ops_status_grace_idx');
        });

        Schema::create('organization_plan_addons', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->string('addon', 40);
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('pending_quantity')->nullable();
            $table->dateTime('pending_effective_at_utc', 6)->nullable();
            $table->string('provider_item_id', 191)->nullable()->unique('opa_provider_item_uq');
            $table->timestamps(6);
            $table->unique(['organization_id', 'addon'], 'opa_org_addon_uq');
            $table->foreign('organization_id', 'opa_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::create('plan_promotion_codes', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('created_by_user_id', 16, true)->nullable();
            $table->binary('code_hash', 32)->unique('ppc_code_hash_uq');
            $table->string('code_hint', 24);
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redemption_count')->default(0);
            $table->dateTime('expires_at_utc', 6)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps(6);
            $table->foreign('created_by_user_id', 'ppc_creator_fk')->references('id')->on('users')->nullOnDelete();
            $table->index(['is_active', 'expires_at_utc'], 'ppc_active_expiry_idx');
        });

        Schema::create('organization_plan_grants', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->binary('promotion_code_id', 16, true)->nullable();
            $table->binary('granted_by_user_id', 16, true)->nullable();
            $table->string('source', 24);
            $table->string('reason', 255)->nullable();
            $table->dateTime('starts_at_utc', 6);
            $table->dateTime('ends_at_utc', 6)->nullable();
            $table->dateTime('revoked_at_utc', 6)->nullable();
            $table->timestamps(6);
            $table->foreign('organization_id', 'opg_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('promotion_code_id', 'opg_code_fk')->references('id')->on('plan_promotion_codes')->nullOnDelete();
            $table->foreign('granted_by_user_id', 'opg_granter_fk')->references('id')->on('users')->nullOnDelete();
            $table->index(['organization_id', 'revoked_at_utc', 'ends_at_utc'], 'opg_org_active_idx');
        });

        Schema::create('plan_promotion_redemptions', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('promotion_code_id', 16, true);
            $table->binary('organization_id', 16, true);
            $table->binary('redeemed_by_user_id', 16, true)->nullable();
            $table->timestamps(6);
            $table->unique(['promotion_code_id', 'organization_id'], 'ppr_code_org_uq');
            $table->foreign('promotion_code_id', 'ppr_code_fk')->references('id')->on('plan_promotion_codes')->cascadeOnDelete();
            $table->foreign('organization_id', 'ppr_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('redeemed_by_user_id', 'ppr_user_fk')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('plan_usage_months', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->date('period_start');
            $table->unsignedInteger('booking_count')->default(0);
            $table->unsignedInteger('distance_lookup_count')->default(0);
            $table->timestamps(6);
            $table->unique(['organization_id', 'period_start'], 'pum_org_period_uq');
            $table->foreign('organization_id', 'pum_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::create('plan_audit_events', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true)->nullable();
            $table->binary('actor_user_id', 16, true)->nullable();
            $table->string('event', 80);
            $table->json('details')->nullable();
            $table->dateTime('created_at', 6);
            $table->foreign('organization_id', 'pae_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('actor_user_id', 'pae_actor_fk')->references('id')->on('users')->nullOnDelete();
            $table->index(['organization_id', 'created_at'], 'pae_org_created_idx');
        });

        Schema::create('plan_webhook_events', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->string('provider_event_id', 191)->unique('pwe_provider_event_uq');
            $table->string('event_type', 100);
            $table->dateTime('processed_at_utc', 6);
            $table->timestamps(6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_webhook_events');
        Schema::dropIfExists('plan_audit_events');
        Schema::dropIfExists('plan_usage_months');
        Schema::dropIfExists('plan_promotion_redemptions');
        Schema::dropIfExists('organization_plan_grants');
        Schema::dropIfExists('plan_promotion_codes');
        Schema::dropIfExists('organization_plan_addons');
        Schema::dropIfExists('organization_plan_subscriptions');

        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn('hide_platform_branding');
        });
    }
};
