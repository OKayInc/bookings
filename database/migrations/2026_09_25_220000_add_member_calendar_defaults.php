<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('external_calendars', function (Blueprint $table): void {
            $table->boolean('is_owned')->nullable();
            $table->boolean('is_default_write')->default(false);
        });
        Schema::table('appointment_type_resources', function (Blueprint $table): void {
            $table->boolean('calendar_defaults_disabled')->default(false);
        });

        // Only primary Google calendars have reliable ownership metadata in old rows.
        // Refresh existing connections to determine secondary/Outlook ownership.
        // Do not infer ownership from read/write or Google's ACL "owner" role.
        DB::table('external_calendars')->where('is_primary', true)
            ->whereIn('calendar_connection_id', DB::table('calendar_connections')
                ->select('id')->where('provider', 'google'))
            ->update(['is_owned' => true]);
        // Existing appointment_type_calendars rows are preserved and take precedence.
    }

    public function down(): void
    {
        Schema::table('appointment_type_resources', fn (Blueprint $table) => $table->dropColumn('calendar_defaults_disabled'));
        Schema::table('external_calendars', fn (Blueprint $table) => $table->dropColumn(['is_owned', 'is_default_write']));
    }
};
