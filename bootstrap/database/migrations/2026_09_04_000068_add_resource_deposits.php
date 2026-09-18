<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('resources', function (Blueprint $table): void {
            $table->unsignedBigInteger('deposit_amount_minor')->nullable()->after('quantity_enabled');
        });

        Schema::table('appointment_question_resource_rule_resources', function (Blueprint $table): void {
            // NULL inherits the resource default; zero is an explicit no-deposit override.
            $table->unsignedBigInteger('deposit_amount_minor')->nullable()->after('resource_id');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->unsignedBigInteger('deposit_minor')->default(0)->after('price_minor');
            $table->unsignedBigInteger('deposit_refunded_minor')->default(0)->after('refunded_minor');
        });

        Schema::table('payment_transactions', function (Blueprint $table): void {
            $table->unsignedBigInteger('deposit_amount_minor')->default(0)->after('amount_minor');
        });

        Schema::table('payment_refunds', function (Blueprint $table): void {
            $table->string('refund_type', 24)->default('general')->after('status');
            $table->index(['booking_id', 'refund_type', 'status'], 'pref_booking_type_status_idx');
        });

        Schema::create('booking_resource_deposits', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('booking_id', 16, true);
            $table->binary('resource_id', 16, true)->nullable();
            $table->char('resource_uuid_snapshot', 36)->nullable();
            $table->string('resource_name', 180);
            $table->char('question_uuid_snapshot', 36)->nullable();
            $table->string('question_label')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_amount_minor');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('configuration_source', 24);
            $table->timestamps(6);

            $table->index(['booking_id', 'created_at'], 'brd_booking_created_idx');
            $table->foreign('booking_id', 'brd_booking_fk')->references('id')->on('bookings')->cascadeOnDelete();
            $table->foreign('resource_id', 'brd_resource_fk')->references('id')->on('resources')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_resource_deposits');

        Schema::table('payment_refunds', function (Blueprint $table): void {
            $table->dropIndex('pref_booking_type_status_idx');
            $table->dropColumn('refund_type');
        });

        Schema::table('payment_transactions', function (Blueprint $table): void {
            $table->dropColumn('deposit_amount_minor');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['deposit_minor', 'deposit_refunded_minor']);
        });

        Schema::table('appointment_question_resource_rule_resources', function (Blueprint $table): void {
            $table->dropColumn('deposit_amount_minor');
        });

        Schema::table('resources', function (Blueprint $table): void {
            $table->dropColumn('deposit_amount_minor');
        });
    }
};
