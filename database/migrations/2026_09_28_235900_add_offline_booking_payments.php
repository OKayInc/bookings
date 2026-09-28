<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointment_types', function (Blueprint $table): void {
            $table->boolean('offline_payment_enabled')->default(false);
            $table->unsignedInteger('offline_payment_window_minutes')->default(1440);
            $table->text('offline_payment_instructions')->nullable();
        });
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dateTime('offline_payment_selected_at_utc', 6)->nullable();
            $table->dateTime('offline_payment_deadline_at_utc', 6)->nullable();
            $table->text('offline_payment_instructions')->nullable();
            $table->dateTime('payment_expiry_notified_at_utc', 6)->nullable();
            $table->dateTime('balance_review_requested_at_utc', 6)->nullable();
            $table->dateTime('balance_followup_at_utc', 6)->nullable();
            $table->dateTime('balance_followup_closed_at_utc', 6)->nullable();
            $table->index(['balance_followup_closed_at_utc', 'balance_followup_at_utc'], 'booking_balance_followup_idx');
        });
        Schema::create('booking_payment_actions', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->binary('booking_id', 16, true);
            $table->binary('actor_person_id', 16, true)->nullable();
            $table->binary('payment_transaction_id', 16, true)->nullable();
            $table->char('idempotency_key', 36)->unique('bpa_idempotency_uq');
            $table->string('action', 40);
            $table->string('reference', 191)->nullable();
            $table->char('reference_hash', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('customer_notified_at_utc', 6)->nullable();
            $table->dateTime('staff_notified_at_utc', 6)->nullable();
            $table->timestamps(6);
            $table->index(['booking_id', 'action', 'created_at'], 'bpa_booking_action_idx');
            $table->index(['booking_id', 'reference_hash'], 'bpa_booking_reference_idx');
            $table->index(['action', 'staff_notified_at_utc'], 'bpa_staff_notice_idx');
            $table->index(['action', 'customer_notified_at_utc'], 'bpa_customer_notice_idx');
            $table->foreign('organization_id', 'bpa_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('booking_id', 'bpa_booking_fk')->references('id')->on('bookings')->cascadeOnDelete();
            $table->foreign('actor_person_id', 'bpa_actor_fk')->references('id')->on('persons')->nullOnDelete();
            $table->foreign('payment_transaction_id', 'bpa_payment_fk')->references('id')->on('payment_transactions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('payment_transactions')->where('provider', 'offline')->exists()) {
            throw new RuntimeException('Offline receipts exist. Do not roll back payment support without a reviewed accounting/data migration.');
        }
        Schema::dropIfExists('booking_payment_actions');
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex('booking_balance_followup_idx');
            $table->dropColumn(['offline_payment_selected_at_utc', 'offline_payment_deadline_at_utc',
                'offline_payment_instructions', 'payment_expiry_notified_at_utc', 'balance_review_requested_at_utc',
                'balance_followup_at_utc', 'balance_followup_closed_at_utc']);
        });
        Schema::table('appointment_types', function (Blueprint $table): void {
            $table->dropColumn(['offline_payment_enabled', 'offline_payment_window_minutes', 'offline_payment_instructions']);
        });
    }
};
