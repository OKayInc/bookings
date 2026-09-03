<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('coupon_offers', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('discount_type', 20);
            $table->unsignedBigInteger('amount_minor')->nullable();
            $table->unsignedInteger('percentage_bps')->nullable();
            $table->unsignedBigInteger('purchase_price_minor');
            $table->boolean('applies_to_all')->default(true);
            $table->date('expires_on')->nullable();
            $table->boolean('is_public')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps(6);
            $table->index(['organization_id', 'is_public', 'is_active'], 'co_org_public_active_idx');
            $table->foreign('organization_id', 'co_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::create('coupon_offer_appointment_type', function (Blueprint $table): void {
            $table->binary('coupon_offer_id', 16, true);
            $table->binary('appointment_type_id', 16, true);
            $table->primary(['coupon_offer_id', 'appointment_type_id'], 'coat_pk');
            $table->foreign('coupon_offer_id', 'coat_offer_fk')->references('id')->on('coupon_offers')->cascadeOnDelete();
            $table->foreign('appointment_type_id', 'coat_type_fk')->references('id')->on('appointment_types')->cascadeOnDelete();
        });

        Schema::create('coupons', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->binary('coupon_offer_id', 16, true)->nullable();
            $table->binary('created_by_person_id', 16, true)->nullable();
            $table->string('source', 20);
            $table->string('status', 20);
            $table->text('code');
            $table->binary('code_hash', 32, true);
            $table->text('view_token');
            $table->binary('view_token_hash', 32, true)->unique('coupon_view_token_uq');
            $table->string('discount_type', 20);
            $table->unsignedBigInteger('amount_minor')->nullable();
            $table->unsignedBigInteger('remaining_amount_minor')->nullable();
            $table->unsignedInteger('percentage_bps')->nullable();
            $table->boolean('applies_to_all')->default(true);
            $table->date('expires_on')->nullable();
            $table->string('purchaser_name', 240)->nullable();
            $table->string('purchaser_email', 254)->nullable();
            $table->string('recipient_name', 240)->nullable();
            $table->string('recipient_email', 254)->nullable();
            $table->text('message')->nullable();
            $table->string('delivery_method', 20);
            $table->string('password_hash');
            $table->dateTime('activated_at_utc', 6)->nullable();
            $table->dateTime('delivered_at_utc', 6)->nullable();
            $table->dateTime('destroyed_at_utc', 6)->nullable();
            $table->binary('destroyed_by_person_id', 16, true)->nullable();
            $table->text('destruction_reason')->nullable();
            $table->dateTime('refunded_at_utc', 6)->nullable();
            $table->timestamps(6);
            $table->unique(['organization_id', 'code_hash'], 'coupon_org_code_uq');
            $table->index(['organization_id', 'status', 'expires_on'], 'coupon_org_status_expiry_idx');
            $table->foreign('organization_id', 'coupon_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('coupon_offer_id', 'coupon_offer_fk')->references('id')->on('coupon_offers')->nullOnDelete();
            $table->foreign('created_by_person_id', 'coupon_creator_fk')->references('id')->on('persons')->nullOnDelete();
            $table->foreign('destroyed_by_person_id', 'coupon_destroyer_fk')->references('id')->on('persons')->nullOnDelete();
        });

        Schema::create('coupon_appointment_type', function (Blueprint $table): void {
            $table->binary('coupon_id', 16, true);
            $table->binary('appointment_type_id', 16, true);
            $table->primary(['coupon_id', 'appointment_type_id'], 'cat_pk');
            $table->foreign('coupon_id', 'cat_coupon_fk')->references('id')->on('coupons')->cascadeOnDelete();
            $table->foreign('appointment_type_id', 'cat_type_fk')->references('id')->on('appointment_types')->cascadeOnDelete();
        });

        Schema::create('coupon_redemptions', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->binary('coupon_id', 16, true);
            $table->binary('booking_id', 16, true)->unique('cr_booking_uq');
            $table->unsignedBigInteger('discount_minor');
            $table->unsignedBigInteger('balance_before_minor')->nullable();
            $table->unsignedBigInteger('balance_after_minor')->nullable();
            $table->dateTime('redeemed_at_utc', 6);
            $table->timestamps(6);
            $table->index(['coupon_id', 'redeemed_at_utc'], 'cr_coupon_redeemed_idx');
            $table->foreign('organization_id', 'cr_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('coupon_id', 'cr_coupon_fk')->references('id')->on('coupons')->restrictOnDelete();
            $table->foreign('booking_id', 'cr_booking_fk')->references('id')->on('bookings')->restrictOnDelete();
        });

        Schema::table('payment_transactions', function (Blueprint $table): void {
            $table->dropForeign('pt_booking_fk');
        });
        Schema::table('payment_transactions', function (Blueprint $table): void {
            $table->binary('booking_id', 16, true)->nullable()->change();
            $table->binary('coupon_id', 16, true)->nullable()->after('booking_id');
            $table->index(['coupon_id', 'status'], 'pt_coupon_status_idx');
            $table->foreign('booking_id', 'pt_booking_r3_fk')->references('id')->on('bookings')->cascadeOnDelete();
            $table->foreign('coupon_id', 'pt_coupon_fk')->references('id')->on('coupons')->cascadeOnDelete();
        });

        Schema::table('payment_refunds', function (Blueprint $table): void {
            $table->dropForeign('pref_booking_fk');
        });
        Schema::table('payment_refunds', function (Blueprint $table): void {
            $table->binary('booking_id', 16, true)->nullable()->change();
            $table->binary('coupon_id', 16, true)->nullable()->after('booking_id');
            $table->index(['coupon_id', 'status'], 'pref_coupon_status_idx');
            $table->foreign('booking_id', 'pref_booking_r3_fk')->references('id')->on('bookings')->cascadeOnDelete();
            $table->foreign('coupon_id', 'pref_coupon_fk')->references('id')->on('coupons')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        DB::table('payment_refunds')->whereNotNull('coupon_id')->delete();
        DB::table('payment_transactions')->whereNotNull('coupon_id')->delete();
        Schema::table('payment_refunds', function (Blueprint $table): void {
            $table->dropForeign('pref_coupon_fk');
            $table->dropForeign('pref_booking_r3_fk');
            $table->dropIndex('pref_coupon_status_idx');
            $table->dropColumn('coupon_id');
        });
        Schema::table('payment_refunds', function (Blueprint $table): void {
            $table->binary('booking_id', 16, true)->nullable(false)->change();
        });
        DB::statement('ALTER TABLE `payment_refunds` ADD CONSTRAINT `pref_booking_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE');
        Schema::table('payment_transactions', function (Blueprint $table): void {
            $table->dropForeign('pt_coupon_fk');
            $table->dropForeign('pt_booking_r3_fk');
            $table->dropIndex('pt_coupon_status_idx');
            $table->dropColumn('coupon_id');
        });
        Schema::table('payment_transactions', function (Blueprint $table): void {
            $table->binary('booking_id', 16, true)->nullable(false)->change();
        });
        DB::statement('ALTER TABLE `payment_transactions` ADD CONSTRAINT `pt_booking_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE');
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupon_appointment_type');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('coupon_offer_appointment_type');
        Schema::dropIfExists('coupon_offers');
    }
};
