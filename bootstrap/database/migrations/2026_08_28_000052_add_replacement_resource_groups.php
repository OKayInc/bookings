<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointment_type_resources', function (Blueprint $table): void {
            $table->string('replacement_group', 80)->nullable()->after('requirement_mode');
        });

        Schema::table('booking_hold_resources', function (Blueprint $table): void {
            $table->string('replacement_group', 80)->nullable()->after('is_required');
        });

        Schema::table('appointment_resources', function (Blueprint $table): void {
            $table->string('replacement_group', 80)->nullable()->after('is_required');
        });

        Schema::table('resource_confirmations', function (Blueprint $table): void {
            $table->string('replacement_group', 80)->nullable()->after('is_required');
        });
    }

    public function down(): void
    {
        Schema::table('resource_confirmations', function (Blueprint $table): void {
            $table->dropColumn('replacement_group');
        });

        Schema::table('appointment_resources', function (Blueprint $table): void {
            $table->dropColumn('replacement_group');
        });

        Schema::table('booking_hold_resources', function (Blueprint $table): void {
            $table->dropColumn('replacement_group');
        });

        Schema::table('appointment_type_resources', function (Blueprint $table): void {
            $table->dropColumn('replacement_group');
        });
    }
};
