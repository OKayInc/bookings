<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('booking_answers', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('booking_id', 16, true);
            $table->binary('appointment_question_id', 16, true)->nullable();
            $table->char('question_uuid_snapshot', 36);
            $table->string('question_label', 255);
            $table->string('question_type', 32);
            $table->json('value_json')->nullable();
            $table->json('normalized_json')->nullable();
            $table->unsignedInteger('position')->default(1);
            $table->timestamps(6);

            $table->index(['booking_id', 'position'], 'ba_booking_pos_idx');
            $table->foreign('booking_id', 'bans_booking_fk')->references('id')->on('bookings')->cascadeOnDelete();
            $table->foreign('appointment_question_id', 'bans_question_fk')->references('id')->on('appointment_questions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_answers');
    }
};
