<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('resources', function (Blueprint $table): void {
            $table->boolean('is_required_by_default')->default(true)->after('is_active');
        });

        Schema::table('appointment_type_resources', function (Blueprint $table): void {
            $table->string('requirement_mode', 16)->default('inherit')->after('is_required');
        });

        Schema::table('booking_hold_resources', function (Blueprint $table): void {
            $table->boolean('is_required')->default(true)->after('resource_id');
        });

        Schema::table('appointment_resources', function (Blueprint $table): void {
            $table->boolean('is_required')->default(true)->after('resource_id');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_resources', function (Blueprint $table): void {
            $table->dropColumn('is_required');
        });

        Schema::table('booking_hold_resources', function (Blueprint $table): void {
            $table->dropColumn('is_required');
        });

        Schema::table('appointment_type_resources', function (Blueprint $table): void {
            $table->dropColumn('requirement_mode');
        });

        Schema::table('resources', function (Blueprint $table): void {
            $table->dropColumn('is_required_by_default');
        });
    }
};
