<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('attendee_email_templates', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->string('kind', 64);
            $table->string('format', 8);
            $table->string('subject');
            $table->text('body');
            $table->timestamps(6);
            $table->unique(['organization_id', 'kind'], 'attendee_email_org_kind');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });
    }
    public function down(): void { Schema::dropIfExists('attendee_email_templates'); }
};
