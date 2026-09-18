<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->boolean('requires_resource_confirmation')->default(false);
            $table->boolean('cancellation_allowed')->default(true);
            $table->unsignedInteger('cancellation_notice_value')->default(24);
            $table->string('cancellation_notice_unit', 16)->default('hour');
            $table->text('cancellation_policy_text')->nullable();
            $table->dateTime('cancelled_at_utc', 6)->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->boolean('rescheduling_allowed')->default(true);
            $table->unsignedInteger('rescheduling_notice_value')->default(24);
            $table->string('rescheduling_notice_unit', 16)->default('hour');
            $table->unsignedInteger('rescheduling_max_count')->default(0);
            $table->unsignedInteger('reschedule_count')->default(0);
            $table->text('rescheduling_policy_text')->nullable();
        });

        DB::statement('UPDATE bookings b JOIN appointment_types at ON at.id = b.appointment_type_id SET b.requires_resource_confirmation = at.requires_resource_confirmation');
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn([
                'requires_resource_confirmation', 'cancellation_allowed', 'cancellation_notice_value', 'cancellation_notice_unit', 'cancellation_policy_text',
                'cancelled_at_utc', 'cancellation_reason', 'rescheduling_allowed', 'rescheduling_notice_value',
                'rescheduling_notice_unit', 'rescheduling_max_count', 'reschedule_count', 'rescheduling_policy_text',
            ]);
        });
    }
};
