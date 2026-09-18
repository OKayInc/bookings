<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('booking_answer_files', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('booking_answer_id', 16, true);
            $table->binary('booking_id', 16, true);
            $table->string('disk', 32);
            $table->string('path', 1024);
            $table->string('original_name', 255);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->binary('sha256', 32, true);
            $table->unsignedInteger('position')->default(1);
            $table->timestamps(6);

            $table->index(['booking_answer_id', 'position'], 'baf_answer_pos_idx');
            $table->foreign('booking_answer_id', 'baf_answer_fk')->references('id')->on('booking_answers')->cascadeOnDelete();
            $table->foreign('booking_id', 'baf_booking_fk')->references('id')->on('bookings')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_answer_files');
    }
};
