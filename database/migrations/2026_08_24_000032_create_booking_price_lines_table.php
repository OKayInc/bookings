<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('booking_price_lines', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('booking_id', 16, true);
            $table->string('source_type', 32);
            $table->char('source_uuid', 36)->nullable();
            $table->string('label', 255);
            $table->string('line_type', 32);
            $table->decimal('quantity', 20, 4)->default(1);
            $table->unsignedBigInteger('amount_minor');
            $table->json('metadata')->nullable();
            $table->unsignedInteger('position')->default(1);
            $table->timestamps(6);

            $table->index(['booking_id', 'position'], 'bpl_booking_pos_idx');
            $table->foreign('booking_id', 'bpl_booking_fk')->references('id')->on('bookings')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_price_lines');
    }
};
