<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organization_member_invitations', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->binary('invited_by_person_id', 16, true)->nullable();
            $table->binary('accepted_by_person_id', 16, true)->nullable();
            $table->string('email', 254);
            $table->string('email_normalized', 254);
            $table->string('role', 32);
            $table->binary('token_hash', 32, true);
            $table->dateTime('expires_at_utc', 6);
            $table->dateTime('accepted_at_utc', 6)->nullable();
            $table->dateTime('revoked_at_utc', 6)->nullable();
            $table->timestamps(6);

            $table->unique(['organization_id', 'email_normalized'], 'omi_org_email_uq');
            $table->unique('token_hash', 'omi_token_uq');
            $table->index(['organization_id', 'expires_at_utc'], 'omi_org_expires_idx');
            $table->foreign('organization_id', 'omi_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('invited_by_person_id', 'omi_inviter_fk')->references('id')->on('persons')->nullOnDelete();
            $table->foreign('accepted_by_person_id', 'omi_acceptor_fk')->references('id')->on('persons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_member_invitations');
    }
};
