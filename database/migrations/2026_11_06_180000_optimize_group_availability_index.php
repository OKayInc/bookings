<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->index(
                ['appointment_type_id', 'status', 'duration_value', 'starts_at_utc'],
                'appt_type_status_duration_start_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropIndex('appt_type_status_duration_start_idx');
        });
    }
};
