<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appointment_resources', function (Blueprint $table): void {
            $table->binary('appointment_id', 16, true);
            $table->binary('resource_id', 16, true);

            $table->primary(['appointment_id', 'resource_id'], 'apr_primary');
            $table->index(['resource_id', 'appointment_id'], 'apr_resource_idx');
            $table->foreign('appointment_id', 'apr_appt_fk')->references('id')->on('appointments')->cascadeOnDelete();
            $table->foreign('resource_id', 'apr_resource_fk')->references('id')->on('resources')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_resources');
    }
};
