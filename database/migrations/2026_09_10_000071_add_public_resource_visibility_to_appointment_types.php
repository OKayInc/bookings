<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointment_types', function (Blueprint $table): void {
            $table->boolean('show_resources_to_clients')
                ->default(true)
                ->after('requires_resource_confirmation');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_types', function (Blueprint $table): void {
            $table->dropColumn('show_resources_to_clients');
        });
    }
};
