<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organization_conference_settings', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true)->unique();

            $table->text('google_maps_api_key')->nullable();
            $table->text('google_routes_api_key')->nullable();

            $table->string('google_client_id')->nullable();
            $table->text('google_client_secret')->nullable();
            $table->text('google_refresh_token')->nullable();

            $table->string('microsoft_tenant_id')->nullable();
            $table->string('microsoft_client_id')->nullable();
            $table->text('microsoft_client_secret')->nullable();
            $table->string('microsoft_organizer_user_id')->nullable();

            $table->string('zoom_account_id')->nullable();
            $table->string('zoom_client_id')->nullable();
            $table->text('zoom_client_secret')->nullable();
            $table->string('zoom_host_user_id')->nullable();

            $table->string('webex_client_id')->nullable();
            $table->text('webex_client_secret')->nullable();
            $table->text('webex_refresh_token')->nullable();
            $table->string('webex_host_email')->nullable();

            $table->text('custom_meeting_url')->nullable();
            $table->timestamps(6);

            $table->foreign('organization_id', 'ocs_org_fk')
                ->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::table('appointment_types', function (Blueprint $table): void {
            $table->boolean('is_online')->default(false)->after('attendance_mode');
            $table->string('meeting_provider', 32)->nullable()->after('is_online');
            $table->index(['organization_id', 'is_online', 'meeting_provider'], 'at_org_online_provider_idx');
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->string('meeting_provider', 32)->nullable()->after('status');
            $table->string('meeting_external_id', 512)->nullable()->after('meeting_provider');
            $table->text('meeting_join_url')->nullable()->after('meeting_external_id');
            $table->text('meeting_host_url')->nullable()->after('meeting_join_url');
            $table->string('meeting_status', 24)->nullable()->after('meeting_host_url');
            $table->text('meeting_error')->nullable()->after('meeting_status');
            $table->index(['organization_id', 'meeting_provider', 'meeting_status'], 'appt_org_meeting_idx');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropIndex('appt_org_meeting_idx');
            $table->dropColumn([
                'meeting_provider', 'meeting_external_id', 'meeting_join_url',
                'meeting_host_url', 'meeting_status', 'meeting_error',
            ]);
        });

        Schema::table('appointment_types', function (Blueprint $table): void {
            $table->dropIndex('at_org_online_provider_idx');
            $table->dropColumn(['is_online', 'meeting_provider']);
        });

        Schema::dropIfExists('organization_conference_settings');
    }
};
