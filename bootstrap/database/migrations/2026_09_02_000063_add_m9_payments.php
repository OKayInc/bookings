<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organization_payment_settings', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true)->unique('ops_org_uq');
            $table->string('default_provider', 20)->nullable();
            $table->boolean('stripe_enabled')->default(false);
            $table->boolean('stripe_test_mode')->default(true);
            $table->text('stripe_secret_key')->nullable();
            $table->text('stripe_webhook_secret')->nullable();
            $table->boolean('paypal_enabled')->default(false);
            $table->boolean('paypal_sandbox')->default(true);
            $table->text('paypal_client_id')->nullable();
            $table->text('paypal_client_secret')->nullable();
            $table->text('paypal_webhook_id')->nullable();
            $table->timestamps(6);
            $table->foreign('organization_id', 'ops_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::create('payment_rules', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->string('rule_type', 20);
            $table->string('match_type', 20);
            $table->string('pattern', 254);
            $table->string('pattern_normalized', 254);
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps(6);
            $table->unique(['organization_id', 'rule_type', 'match_type', 'pattern_normalized'], 'pr_org_rule_match_uq');
            $table->index(['organization_id', 'is_active', 'rule_type'], 'pr_org_active_type_idx');
            $table->foreign('organization_id', 'pr_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::table('appointment_types', function (Blueprint $table): void {
            $table->string('payment_collection_mode', 20)->default('full')->after('rate_unit');
            $table->string('retainer_type', 20)->nullable()->after('payment_collection_mode');
            $table->unsignedBigInteger('retainer_amount_minor')->nullable()->after('retainer_type');
            $table->unsignedInteger('retainer_percentage_bps')->nullable()->after('retainer_amount_minor');
            $table->unsignedInteger('balance_due_value')->default(0)->after('retainer_percentage_bps');
            $table->string('balance_due_unit', 16)->default('day')->after('balance_due_value');
            $table->unsignedInteger('client_refund_percentage_bps')->default(0)->after('balance_due_unit');
            $table->unsignedInteger('staff_refund_percentage_bps')->default(10000)->after('client_refund_percentage_bps');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('payment_collection_mode', 20)->default('full')->after('currency');
            $table->unsignedBigInteger('initial_payment_due_minor')->default(0)->after('payment_collection_mode');
            $table->dateTime('balance_due_at_utc', 6)->nullable()->after('initial_payment_due_minor');
            $table->unsignedInteger('client_refund_percentage_bps')->default(0)->after('balance_due_at_utc');
            $table->unsignedInteger('staff_refund_percentage_bps')->default(10000)->after('client_refund_percentage_bps');
            $table->boolean('payment_exempt')->default(false)->after('staff_refund_percentage_bps');
            $table->binary('payment_rule_id', 16, true)->nullable()->after('payment_exempt');
            $table->string('payment_status', 24)->default('unpaid')->after('payment_rule_id');
            $table->unsignedBigInteger('paid_minor')->default(0)->after('payment_status');
            $table->unsignedBigInteger('refunded_minor')->default(0)->after('paid_minor');
            $table->index(['organization_id', 'payment_status', 'created_at'], 'booking_org_payment_idx');
            $table->foreign('payment_rule_id', 'booking_payment_rule_fk')->references('id')->on('payment_rules')->nullOnDelete();
        });

        DB::table('bookings')->where('price_minor', '>', 0)->update([
            'initial_payment_due_minor' => DB::raw('price_minor'),
        ]);
        DB::table('bookings')->where('price_minor', 0)->update([
            'payment_status' => 'paid',
        ]);

        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->binary('booking_id', 16, true);
            $table->string('provider', 20);
            $table->string('purpose', 20);
            $table->string('status', 24);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->char('idempotency_key', 36)->unique('pt_idempotency_uq');
            $table->binary('return_token_hash', 32, true);
            $table->string('provider_external_id', 191)->nullable();
            $table->string('provider_capture_id', 191)->nullable();
            $table->text('checkout_url')->nullable();
            $table->text('failure_message')->nullable();
            $table->json('provider_payload')->nullable();
            $table->dateTime('expires_at_utc', 6)->nullable();
            $table->dateTime('completed_at_utc', 6)->nullable();
            $table->timestamps(6);
            $table->unique(['organization_id', 'provider', 'provider_external_id'], 'pt_org_provider_external_uq');
            $table->index(['booking_id', 'status', 'purpose'], 'pt_booking_status_purpose_idx');
            $table->foreign('organization_id', 'pt_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('booking_id', 'pt_booking_fk')->references('id')->on('bookings')->cascadeOnDelete();
        });

        Schema::create('payment_refunds', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->binary('booking_id', 16, true);
            $table->binary('payment_transaction_id', 16, true);
            $table->binary('requested_by_person_id', 16, true)->nullable();
            $table->string('provider', 20);
            $table->string('status', 24);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->char('idempotency_key', 36)->unique('pref_idempotency_uq');
            $table->string('provider_refund_id', 191)->nullable();
            $table->text('reason')->nullable();
            $table->text('failure_message')->nullable();
            $table->json('provider_payload')->nullable();
            $table->dateTime('completed_at_utc', 6)->nullable();
            $table->timestamps(6);
            $table->unique(['organization_id', 'provider', 'provider_refund_id'], 'pref_org_provider_external_uq');
            $table->index(['booking_id', 'status'], 'pref_booking_status_idx');
            $table->foreign('organization_id', 'pref_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('booking_id', 'pref_booking_fk')->references('id')->on('bookings')->cascadeOnDelete();
            $table->foreign('payment_transaction_id', 'pref_transaction_fk')->references('id')->on('payment_transactions')->cascadeOnDelete();
            $table->foreign('requested_by_person_id', 'pref_person_fk')->references('id')->on('persons')->nullOnDelete();
        });

        Schema::create('payment_webhook_events', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->string('provider', 20);
            $table->string('provider_event_id', 191);
            $table->string('event_type', 120);
            $table->json('payload');
            $table->dateTime('processed_at_utc', 6)->nullable();
            $table->text('processing_error')->nullable();
            $table->timestamps(6);
            $table->unique(['organization_id', 'provider', 'provider_event_id'], 'pwe_org_provider_event_uq');
            $table->foreign('organization_id', 'pwe_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payment_refunds');
        Schema::dropIfExists('payment_transactions');

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropForeign('booking_payment_rule_fk');
            $table->dropIndex('booking_org_payment_idx');
            $table->dropColumn([
                'payment_collection_mode', 'initial_payment_due_minor', 'balance_due_at_utc',
                'client_refund_percentage_bps', 'staff_refund_percentage_bps', 'payment_exempt',
                'payment_rule_id', 'payment_status', 'paid_minor', 'refunded_minor',
            ]);
        });

        Schema::table('appointment_types', function (Blueprint $table): void {
            $table->dropColumn([
                'payment_collection_mode', 'retainer_type', 'retainer_amount_minor',
                'retainer_percentage_bps', 'balance_due_value', 'balance_due_unit',
                'client_refund_percentage_bps', 'staff_refund_percentage_bps',
            ]);
        });

        Schema::dropIfExists('payment_rules');
        Schema::dropIfExists('organization_payment_settings');
    }
};
