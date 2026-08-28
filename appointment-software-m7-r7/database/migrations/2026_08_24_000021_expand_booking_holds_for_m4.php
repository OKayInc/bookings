<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('booking_holds', function (Blueprint $table): void {
            $table->binary('appointment_id', 16, true)->nullable()->after('appointment_type_id');
            $table->binary('appointment_type_invitation_id', 16, true)->nullable()->after('appointment_id');
            $table->binary('contract_template_id', 16, true)->nullable()->after('appointment_type_invitation_id');
            $table->unsignedInteger('attendee_count')->default(1)->after('duration_value');

            $table->index(['appointment_id', 'status', 'expires_at_utc'], 'bh_appt_status_exp_idx');
            $table->foreign('appointment_id', 'bh_appt_fk')->references('id')->on('appointments')->cascadeOnDelete();
            $table->foreign('appointment_type_invitation_id', 'bh_invite_fk')->references('id')->on('appointment_type_invitations')->nullOnDelete();
            $table->foreign('contract_template_id', 'bh_contract_fk')->references('id')->on('appointment_contract_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('booking_holds', function (Blueprint $table): void {
            $table->dropForeign('bh_contract_fk');
            $table->dropForeign('bh_invite_fk');
            $table->dropForeign('bh_appt_fk');
            $table->dropIndex('bh_appt_status_exp_idx');
            $table->dropColumn(['appointment_id', 'appointment_type_invitation_id', 'contract_template_id', 'attendee_count']);
        });
    }
};
