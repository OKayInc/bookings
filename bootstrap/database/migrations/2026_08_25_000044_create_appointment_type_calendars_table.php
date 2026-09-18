<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appointment_type_calendars', function (Blueprint $table): void {
            $table->binary('appointment_type_id', 16, true);
            $table->binary('external_calendar_id', 16, true);
            $table->boolean('check_availability')->default(true);
            $table->boolean('create_event')->default(false);
            $table->timestamps(6);

            $table->primary(['appointment_type_id', 'external_calendar_id'], 'atc_primary');
            $table->index(['appointment_type_id', 'check_availability'], 'atc_type_check_idx');
            $table->index(['appointment_type_id', 'create_event'], 'atc_type_create_idx');
            $table->foreign('appointment_type_id', 'atc_type_fk')->references('id')->on('appointment_types')->cascadeOnDelete();
            $table->foreign('external_calendar_id', 'atc_calendar_fk')->references('id')->on('external_calendars')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_type_calendars');
    }
};
