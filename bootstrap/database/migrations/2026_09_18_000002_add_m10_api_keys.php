<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['users', 'organizations'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->char('api_key_hash', 64)->nullable()->unique();
                $table->timestamp('api_key_created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['users', 'organizations'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropUnique(['api_key_hash']);
                $table->dropColumn(['api_key_hash', 'api_key_created_at']);
            });
        }
    }
};
