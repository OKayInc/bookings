<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dateTime('outcome_review_requested_at_utc', 6)->nullable()->after('cancelled_at_utc');
        });

        DB::table('organization_contacts')
            ->whereNotNull('phone')
            ->orderBy('created_at')
            ->orderBy('id')
            ->chunk(500, function ($contacts): void {
                foreach ($contacts as $contact) {
                    $digits = preg_replace('/\\D+/', '', (string) $contact->phone);
                    DB::table('organization_contacts')
                        ->where('id', $contact->id)
                        ->update(['phone_normalized' => $digits !== '' ? $digits : null]);
                }
            });

        Schema::create('booking_outcomes', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->binary('booking_id', 16, true)->unique('booking_outcome_booking_uq');
            $table->string('outcome', 20);
            $table->string('source', 20)->default('dashboard');
            $table->binary('recorded_by_person_id', 16, true)->nullable();
            $table->dateTime('recorded_at_utc', 6);
            $table->timestamps(6);
            $table->index(['organization_id', 'outcome', 'recorded_at_utc'], 'booking_outcome_org_idx');
            $table->foreign('organization_id', 'booking_outcome_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('booking_id', 'booking_outcome_booking_fk')->references('id')->on('bookings')->cascadeOnDelete();
            $table->foreign('recorded_by_person_id', 'booking_outcome_person_fk')->references('id')->on('persons')->nullOnDelete();
        });

        Schema::create('customer_reputation_settings', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true)->unique('customer_rep_settings_org_uq');
            $table->boolean('post_appointment_review_enabled')->default(true);
            $table->json('review_roles')->nullable();
            $table->string('blacklist_mode', 20)->default('suggest');
            $table->unsignedInteger('blacklist_no_show_threshold')->default(2);
            $table->unsignedInteger('blacklist_window_days')->nullable()->default(180);
            $table->string('whitelist_mode', 20)->default('suggest');
            $table->unsignedInteger('whitelist_success_threshold')->default(5);
            $table->unsignedBigInteger('whitelist_min_revenue_minor')->default(0);
            $table->unsignedInteger('whitelist_window_days')->nullable()->default(365);
            $table->unsignedInteger('whitelist_max_no_shows')->default(0);
            $table->unsignedInteger('minimum_reviewed_appointments')->default(2);
            $table->unsignedInteger('policy_entry_expiration_days')->nullable();
            $table->timestamps(6);
            $table->foreign('organization_id', 'customer_rep_settings_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::create('customer_access_entries', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->binary('organization_contact_id', 16, true);
            $table->string('list_type', 20);
            $table->string('status', 20)->default('active');
            $table->string('source', 20)->default('manual');
            $table->string('policy_key', 80)->nullable();
            $table->text('reason')->nullable();
            $table->json('policy_snapshot')->nullable();
            $table->binary('created_by_person_id', 16, true)->nullable();
            $table->dateTime('expires_at_utc', 6)->nullable();
            $table->dateTime('resolved_at_utc', 6)->nullable();
            $table->timestamps(6);
            $table->index(['organization_id', 'organization_contact_id', 'status'], 'customer_access_contact_idx');
            $table->index(['organization_id', 'list_type', 'status'], 'customer_access_list_idx');
            $table->foreign('organization_id', 'customer_access_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('organization_contact_id', 'customer_access_contact_fk')->references('id')->on('organization_contacts')->cascadeOnDelete();
            $table->foreign('created_by_person_id', 'customer_access_person_fk')->references('id')->on('persons')->nullOnDelete();
        });

        Schema::create('customer_access_events', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->binary('organization_contact_id', 16, true);
            $table->binary('customer_access_entry_id', 16, true)->nullable();
            $table->string('event_type', 40);
            $table->string('source', 20);
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->binary('actor_person_id', 16, true)->nullable();
            $table->dateTime('occurred_at_utc', 6);
            $table->timestamps(6);
            $table->index(['organization_id', 'organization_contact_id', 'occurred_at_utc'], 'customer_access_event_contact_idx');
            $table->foreign('organization_id', 'customer_access_event_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('organization_contact_id', 'customer_access_event_contact_fk')->references('id')->on('organization_contacts')->cascadeOnDelete();
            $table->foreign('customer_access_entry_id', 'customer_access_event_entry_fk')->references('id')->on('customer_access_entries')->nullOnDelete();
            $table->foreign('actor_person_id', 'customer_access_event_actor_fk')->references('id')->on('persons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_access_events');
        Schema::dropIfExists('customer_access_entries');
        Schema::dropIfExists('customer_reputation_settings');
        Schema::dropIfExists('booking_outcomes');
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn('outcome_review_requested_at_utc');
        });
    }
};
