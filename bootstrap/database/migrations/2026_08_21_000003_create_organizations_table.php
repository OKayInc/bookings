<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->string('name', 180);
            $table->string('slug', 180)->unique();
            $table->string('timezone', 64);
            $table->char('currency', 3)->default('CAD');
            $table->string('logo_path')->nullable();
            $table->timestamps(6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
