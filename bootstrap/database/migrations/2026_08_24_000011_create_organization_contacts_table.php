<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organization_contacts', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);

            $table->string('first_name', 120)->nullable();
            $table->string('last_name', 120)->nullable();
            $table->string('email', 254);
            $table->string('email_normalized', 254);
            $table->dateTime('email_verified_at', 6)->nullable();

            $table->string('phone', 64)->nullable();
            $table->string('phone_normalized', 32)->nullable();
            $table->dateTime('phone_verified_at', 6)->nullable();

            $table->string('address_text', 1000)->nullable();
            $table->string('google_place_id', 255)->nullable();
            $table->json('address_metadata')->nullable();
            $table->timestamps(6);

            $table->unique(['organization_id', 'email_normalized'], 'oc_org_email_uq');
            $table->index(['organization_id', 'phone_normalized'], 'oc_org_phone_idx');
            $table->index(['organization_id', 'google_place_id'], 'oc_org_place_idx');
            $table->foreign('organization_id', 'oc_org_fk')
                ->references('id')->on('organizations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_contacts');
    }
};
