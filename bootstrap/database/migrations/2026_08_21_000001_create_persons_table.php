<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('persons', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('primary_email', 254)->index();
            $table->string('primary_phone', 32)->nullable()->index();
            $table->string('timezone', 64)->default('UTC');
            $table->string('locale', 12)->default('en');
            $table->timestamps(6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('persons');
    }
};
