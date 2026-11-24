<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('resource_share_invitations', function (Blueprint $table): void {
            $table->id();
            $table->binary('resource_id', 16, true);
            $table->binary('recipient_organization_id', 16, true)->nullable();
            $table->string('recipient_email', 254);
            $table->char('token_hash', 64)->unique();
            $table->string('status', 16)->default('pending');
            $table->dateTime('expires_at');
            $table->timestamps();
            $table->foreign('resource_id', 'rsi_resource_fk')->references('id')->on('resources')->cascadeOnDelete();
            $table->foreign('recipient_organization_id', 'rsi_recipient_fk')->references('id')->on('organizations')->cascadeOnDelete();
            $table->index(['resource_id', 'status'], 'rsi_resource_status');
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('resource_share_invitations')->where('status', 'accepted')->exists()) {
            throw new \RuntimeException('End all external resource shares before rolling back.');
        }
        Schema::dropIfExists('resource_share_invitations');
    }
};
