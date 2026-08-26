<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appointment_type_resources', function (Blueprint $table): void {
            $table->binary('appointment_type_id', 16, true);
            $table->binary('resource_id', 16, true);
            $table->boolean('is_required')->default(true);
            $table->timestamps(6);

            $table->primary(['appointment_type_id', 'resource_id'], 'atr_primary');
            $table->foreign('appointment_type_id')->references('id')->on('appointment_types')->cascadeOnDelete();
            $table->foreign('resource_id')->references('id')->on('resources')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_type_resources');
    }
};
