<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appointment_type_invitations', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->binary('appointment_type_id', 16, true);
            $table->binary('created_by_person_id', 16, true)->nullable();
            $table->char('token_hash', 64);
            $table->string('recipient_email', 254)->nullable();
            $table->dateTime('expires_at', 6)->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps(6);

            $table->unique('token_hash', 'ati_token_uq');
            $table->index(['appointment_type_id', 'is_active'], 'ati_type_active_idx');
            $table->index(['organization_id', 'created_at'], 'ati_org_created_idx');
            $table->foreign('organization_id', 'ati_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('appointment_type_id', 'ati_type_fk')->references('id')->on('appointment_types')->cascadeOnDelete();
            $table->foreign('created_by_person_id', 'ati_person_fk')->references('id')->on('persons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_type_invitations');
    }
};
