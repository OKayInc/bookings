<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purge_file_deletions', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->string('disk');
            $table->text('path');
            $table->timestamps();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });
    }
    public function down(): void { Schema::dropIfExists('purge_file_deletions'); }
};
