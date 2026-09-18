<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointment_types', function (Blueprint $table): void {
            $table->boolean('cancellation_allowed')->default(true);
            $table->unsignedInteger('cancellation_notice_value')->default(24);
            $table->string('cancellation_notice_unit', 16)->default('hour');
            $table->text('cancellation_policy_text')->nullable();

            $table->boolean('rescheduling_allowed')->default(true);
            $table->unsignedInteger('rescheduling_notice_value')->default(24);
            $table->string('rescheduling_notice_unit', 16)->default('hour');
            $table->unsignedInteger('rescheduling_max_count')->default(0);
            $table->text('rescheduling_policy_text')->nullable();

            $table->boolean('reminder_enabled')->default(false);
            $table->string('reminder_threshold_basis', 20)->default('lead_time');
            $table->unsignedInteger('reminder_threshold_days')->default(7);
            $table->unsignedInteger('reminder_before_value')->default(1);
            $table->string('reminder_before_unit', 16)->default('day');
            $table->boolean('reminder_clients')->default(true);
            $table->boolean('reminder_resources')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('appointment_types', function (Blueprint $table): void {
            $table->dropColumn([
                'cancellation_allowed', 'cancellation_notice_value', 'cancellation_notice_unit', 'cancellation_policy_text',
                'rescheduling_allowed', 'rescheduling_notice_value', 'rescheduling_notice_unit', 'rescheduling_max_count', 'rescheduling_policy_text',
                'reminder_enabled', 'reminder_threshold_basis', 'reminder_threshold_days', 'reminder_before_value', 'reminder_before_unit',
                'reminder_clients', 'reminder_resources',
            ]);
        });
    }
};
