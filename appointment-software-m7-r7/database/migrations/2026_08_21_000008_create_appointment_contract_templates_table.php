<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appointment_contract_templates', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->binary('appointment_type_id', 16, true);
            $table->binary('uploaded_by_person_id', 16, true)->nullable();
            $table->string('disk', 64)->default('local');
            $table->string('path', 1024);
            $table->string('original_name', 255);
            $table->string('mime_type', 191)->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->boolean('is_active')->default(true);
            // MariaDB UNIQUE permits multiple NULLs. active_slot=1 for the current
            // version and NULL for historical versions enforces one active row.
            $table->unsignedTinyInteger('active_slot')->nullable()->default(1);
            $table->timestamp('superseded_at', 6)->nullable();
            $table->timestamps(6);

            $table->unique(['appointment_type_id', 'active_slot'], 'act_type_active_uq');
            $table->index(['appointment_type_id', 'is_active'], 'act_type_active_idx');
            $table->index(['organization_id', 'created_at'], 'act_org_created_idx');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('appointment_type_id')->references('id')->on('appointment_types')->cascadeOnDelete();
            $table->foreign('uploaded_by_person_id')->references('id')->on('persons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_contract_templates');
    }
};
