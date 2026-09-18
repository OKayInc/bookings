<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appointment_types', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->string('name', 180);
            $table->string('slug', 180);
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('visibility', 32)->default('public');
            $table->string('access_password')->nullable();
            $table->string('public_token', 64)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps(6);

            $table->unique(['organization_id', 'slug']);
            $table->index(['organization_id', 'visibility', 'is_active']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_types');
    }
};
