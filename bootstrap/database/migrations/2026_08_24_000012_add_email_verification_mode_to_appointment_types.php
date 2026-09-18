<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointment_types', function (Blueprint $table): void {
            $table->string('email_verification_mode', 32)
                ->default('before_confirmation')
                ->after('requires_resource_confirmation');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_types', function (Blueprint $table): void {
            $table->dropColumn('email_verification_mode');
        });
    }
};
