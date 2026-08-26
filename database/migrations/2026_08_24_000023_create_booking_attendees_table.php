<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('booking_attendees', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('booking_id', 16, true);
            $table->unsignedInteger('position');
            $table->boolean('is_primary')->default(false);
            $table->string('first_name', 120)->nullable();
            $table->string('last_name', 120)->nullable();
            $table->string('email', 254)->nullable();
            $table->timestamps(6);

            $table->unique(['booking_id', 'position'], 'ba_booking_position_uq');
            $table->foreign('booking_id', 'ba_booking_fk')->references('id')->on('bookings')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_attendees');
    }
};
