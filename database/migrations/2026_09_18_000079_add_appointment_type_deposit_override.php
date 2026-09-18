<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointment_types', function (Blueprint $table): void {
            // Null uses automatic deposits; zero explicitly waives them.
            $table->unsignedBigInteger('deposit_override_minor')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('appointment_types', fn (Blueprint $table) => $table->dropColumn('deposit_override_minor'));
    }
};
