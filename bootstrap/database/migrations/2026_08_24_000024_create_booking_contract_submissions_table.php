<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('booking_contract_submissions', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->binary('booking_id', 16, true);
            $table->binary('contract_template_id', 16, true);
            $table->binary('reviewed_by_person_id', 16, true)->nullable();
            $table->string('status', 24)->default('pending');
            $table->text('review_notes')->nullable();
            $table->dateTime('submitted_at_utc', 6);
            $table->dateTime('reviewed_at_utc', 6)->nullable();
            $table->timestamps(6);

            $table->index(['booking_id', 'submitted_at_utc'], 'bcs_booking_submitted_idx');
            $table->index(['organization_id', 'status', 'created_at'], 'bcs_org_status_created_idx');
            $table->foreign('organization_id', 'bcs_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('booking_id', 'bcs_booking_fk')->references('id')->on('bookings')->cascadeOnDelete();
            $table->foreign('contract_template_id', 'bcs_contract_fk')->references('id')->on('appointment_contract_templates')->restrictOnDelete();
            $table->foreign('reviewed_by_person_id', 'bcs_reviewer_fk')->references('id')->on('persons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_contract_submissions');
    }
};
