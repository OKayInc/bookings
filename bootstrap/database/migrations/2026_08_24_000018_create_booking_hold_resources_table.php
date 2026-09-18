<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('booking_hold_resources', function (Blueprint $table): void {
            $table->binary('booking_hold_id', 16, true);
            $table->binary('resource_id', 16, true);

            $table->primary(['booking_hold_id', 'resource_id'], 'bhr_primary');
            $table->index(['resource_id', 'booking_hold_id'], 'bhr_resource_idx');
            $table->foreign('booking_hold_id', 'bhr_hold_fk')->references('id')->on('booking_holds')->cascadeOnDelete();
            $table->foreign('resource_id', 'bhr_resource_fk')->references('id')->on('resources')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_hold_resources');
    }
};
