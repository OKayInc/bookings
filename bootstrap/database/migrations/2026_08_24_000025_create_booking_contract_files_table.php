<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('booking_contract_files', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('booking_contract_submission_id', 16, true);
            $table->unsignedInteger('position');
            $table->string('disk', 64);
            $table->string('path', 1024);
            $table->string('original_name', 255);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->timestamps(6);

            $table->unique(['booking_contract_submission_id', 'position'], 'bcf_submission_position_uq');
            $table->foreign('booking_contract_submission_id', 'bcf_submission_fk')->references('id')->on('booking_contract_submissions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_contract_files');
    }
};
