<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_occurrences', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('appointment_type_id', 16, true);
            $table->dateTime('starts_at_utc', 6);
            $table->string('timezone', 64);
            $table->text('venue')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps(6);
            $table->index(['appointment_type_id', 'is_active', 'starts_at_utc'], 'event_occurrence_lookup');
            $table->foreign('appointment_type_id')->references('id')->on('appointment_types')->cascadeOnDelete();
        });
        Schema::table('appointments', function (Blueprint $table): void {
            $table->binary('event_occurrence_id', 16, true)->nullable();
            $table->foreign('event_occurrence_id')->references('id')->on('event_occurrences')->nullOnDelete();
        });

        // Preserve already scheduled events. Types without an existing event
        // remain unavailable until the owner chooses a fixed date.
        DB::table('appointments')->where('ticketing_enabled', true)->orderBy('id')->each(function ($appointment): void {
            $occurrence = DB::table('event_occurrences')->where('appointment_type_id', $appointment->appointment_type_id)
                ->where('starts_at_utc', $appointment->starts_at_utc)->first();
            $id = $occurrence?->id ?? hex2bin(str_replace('-', '', (string) Str::uuid()));
            if (! $occurrence) {
                $timezone = DB::table('organizations')->where('id', $appointment->organization_id)->value('timezone');
                DB::table('event_occurrences')->insert([
                    'id' => $id, 'appointment_type_id' => $appointment->appointment_type_id,
                    'starts_at_utc' => $appointment->starts_at_utc, 'timezone' => $timezone,
                    'venue' => $appointment->event_location, 'is_active' => $appointment->status === 'scheduled',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::table('appointments')->where('id', $appointment->id)->update(['event_occurrence_id' => $id]);
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropForeign(['event_occurrence_id']);
            $table->dropColumn('event_occurrence_id');
        });
        Schema::dropIfExists('event_occurrences');
    }
};
