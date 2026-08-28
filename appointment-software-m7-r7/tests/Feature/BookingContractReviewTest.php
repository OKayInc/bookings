<?php

namespace Tests\Feature;

use App\Domain\Availability\AvailabilityScheduleService;
use App\Domain\Bookings\BookingCreationService;
use App\Domain\Bookings\PublicBookingHoldService;
use App\Domain\Contracts\ContractTemplateService;
use App\Enums\AvailabilityScope;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\AppointmentType;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookingContractReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_signed_contract_is_manually_reviewed(): void
    {
        Notification::fake();
        Storage::fake('local');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 12:00 UTC'));

        $organization = Organization::factory()->create(['timezone' => 'America/Toronto']);
        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(), 'name' => 'Contract Session', 'slug' => 'contract-session',
            'visibility' => 'public', 'attendance_mode' => 'single', 'capacity' => 1,
            'duration_mode' => 'fixed', 'duration_unit' => 'minute', 'duration_value' => 60,
            'start_interval_minutes' => 60, 'buffer_before_minutes' => 0, 'buffer_after_minutes' => 0,
            'pricing_mode' => 'free', 'email_verification_mode' => 'none', 'is_active' => true,
        ]);
        app(ContractTemplateService::class)->replace(
            $type->load('organization'),
            UploadedFile::fake()->create('contract.pdf', 20, 'application/pdf'),
        );
        app(AvailabilityScheduleService::class)->save($organization, AvailabilityScope::Organization, $organization, 'America/Toronto', true, [
            ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '12:00'],
        ]);

        $lease = app(PublicBookingHoldService::class)->acquire(
            $type->fresh(['organization', 'resources', 'contractTemplate']),
            CarbonImmutable::parse('2026-08-31 09:00', 'America/Toronto')->utc(),
            60, 'America/Toronto', 1,
        );
        $result = app(BookingCreationService::class)->createFromHold(
            $lease->token,
            ['first_name' => 'Signed', 'last_name' => 'Client', 'email' => 'signed@example.test'],
            [],
            [UploadedFile::fake()->create('page1.jpg', 20, 'image/jpeg')],
        );
        $this->assertSame('pending_contract_review', $result->booking->status->value);

        $user = User::factory()->create();
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(), 'person_id' => $user->person_id,
            'role' => MembershipRole::Owner, 'status' => MembershipStatus::Active,
        ]);
        $submission = $result->booking->contractSubmissions()->firstOrFail();

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('bookings.contract.review', [$result->booking, $submission]), [
                'status' => 'approved', 'review_notes' => 'Signature reviewed.',
            ])
            ->assertRedirect();

        $this->assertSame('approved', $submission->fresh()->status->value);
        $this->assertSame('confirmed', $result->booking->fresh()->status->value);
    }
}
