<?php

namespace Tests\Feature;

use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\Organization;
use App\Models\OrganizationContact;
use App\Models\OrganizationMembership;
use App\Models\Resource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class M9R5DashboardTest extends TestCase
{
    use RefreshDatabase;

    private int $referenceSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-04 13:00:00 UTC'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_default_list_is_chronological_limited_and_scoped_to_the_active_organization(): void
    {
        [$user, $organization] = $this->context();
        $later = $this->booking($organization, '2026-09-05 09:00');
        $next = $this->booking($organization, '2026-09-04 10:00');
        $ended = $this->booking($organization, '2026-09-04 08:00');
        $outside = $this->booking($organization, '2026-09-11 00:00');
        $otherOrganization = Organization::factory()->create();
        $foreign = $this->booking($otherOrganization, '2026-09-04 10:00');
        OrganizationMembership::create([
            'organization_id' => $otherOrganization->getKey(),
            'person_id' => $user->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);

        $this->get(route('dashboard'))->assertOk()
            ->assertViewHas('range', 'week')
            ->assertViewHas('perPage', 10)
            ->assertViewHas('upcomingBookings', fn ($rows) => $rows->pluck('reference')->all() === [$next->reference, $later->reference])
            ->assertSeeInOrder([$next->reference, $later->reference])
            ->assertDontSee($ended->reference)
            ->assertDontSee($outside->reference)
            ->assertDontSee($foreign->reference);
    }

    #[DataProvider('dateRanges')]
    public function test_date_filters_use_exclusive_local_calendar_boundaries(string $range, string $now, string $endUtc): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse($now, 'UTC'));
        [, $organization] = $this->context();
        $boundary = CarbonImmutable::parse($endUtc, 'UTC');
        $inside = $this->booking($organization, $boundary->subMinute());
        $outside = $this->booking($organization, $boundary);

        $this->get(route('dashboard', ['range' => $range]))->assertOk()
            ->assertViewHas('rangeEnd', fn ($value) => $value->utc()->format('Y-m-d H:i:s') === $endUtc)
            ->assertSee($inside->reference)
            ->assertDontSee($outside->reference);
    }

    public static function dateRanges(): array
    {
        return [
            'today' => ['today', '2026-09-04 13:00:00', '2026-09-05 04:00:00'],
            'today and tomorrow' => ['tomorrow', '2026-09-04 13:00:00', '2026-09-06 04:00:00'],
            'week' => ['week', '2026-09-04 13:00:00', '2026-09-11 04:00:00'],
            'month' => ['month', '2026-09-04 13:00:00', '2026-10-04 04:00:00'],
            'spring DST day' => ['today', '2026-03-08 05:30:00', '2026-03-09 04:00:00'],
            'autumn DST day' => ['today', '2026-11-01 04:30:00', '2026-11-02 05:00:00'],
            'month end does not overflow' => ['month', '2026-01-31 14:00:00', '2026-02-28 05:00:00'],
        ];
    }

    public function test_all_includes_distant_and_cancelled_bookings_but_excludes_finished_events(): void
    {
        [, $organization] = $this->context();
        $distant = $this->booking($organization, '2028-01-01 10:00');
        $cancelled = $this->booking($organization, '2026-09-04 11:00', ['status' => BookingStatus::Cancelled]);
        $declined = $this->booking($organization, '2026-09-04 12:00', ['status' => BookingStatus::Declined]);
        $ended = $this->booking($organization, '2026-09-03 11:00');

        $this->get(route('dashboard', ['range' => 'all']))->assertOk()
            ->assertViewHas('rangeEnd', null)
            ->assertSeeInOrder([$cancelled->reference, $declined->reference, $distant->reference])
            ->assertDontSee($ended->reference);
    }

    public function test_overnight_in_progress_events_show_both_dates_and_finished_events_are_excluded(): void
    {
        [, $organization] = $this->context();
        $overnight = $this->booking($organization, '2026-09-03 22:00', [], '2026-09-04 10:00');
        $finished = $this->booking($organization, '2026-09-04 08:00');

        $this->get(route('dashboard', ['range' => 'today']))->assertOk()
            ->assertViewHas('upcomingBookings', fn ($rows) => $rows->total() === 1)
            ->assertSee($overnight->reference)
            ->assertSee('Thu, Sep 3, 2026')
            ->assertSee('Fri, Sep 4, 2026 10:00 AM EDT')
            ->assertSee('In progress')
            ->assertDontSee($finished->reference);
    }

    public function test_employees_only_see_bookings_assigned_to_their_person_resources(): void
    {
        [$employee, $organization] = $this->context(MembershipRole::Employee);
        $assigned = $this->booking($organization, '2026-09-04 10:00');
        $unassigned = $this->booking($organization, '2026-09-04 11:00');
        $foreign = $this->booking(Organization::factory()->create(), '2026-09-04 12:00');
        foreach (['Camera operator', 'Photographer'] as $name) {
            $resource = Resource::create([
                'organization_id' => $organization->getKey(),
                'person_id' => $employee->person_id,
                'type' => 'person',
                'name' => $name,
                'timezone' => $organization->timezone,
                'is_active' => true,
            ]);
            $assigned->appointment->resources()->attach($resource->getKey(), ['is_required' => true]);
            $foreign->appointment->resources()->attach($resource->getKey(), ['is_required' => true]);
        }

        $this->get(route('dashboard'))->assertOk()
            ->assertViewHas('canManageBookings', false)
            ->assertViewHas('upcomingBookings', fn ($rows) => $rows->total() === 1)
            ->assertSee('Bookings assigned to you')
            ->assertSee($assigned->reference)
            ->assertDontSee($unassigned->reference)
            ->assertDontSee($foreign->reference);
    }

    public function test_count_selector_paginates_without_losing_range_or_repeating_bookings(): void
    {
        [, $organization] = $this->context();
        $references = [];
        for ($i = 0; $i < 12; $i++) {
            $references[] = $this->booking($organization, '2026-09-04 10:00')->reference;
        }

        $this->get(route('dashboard', ['range' => 'today']))->assertOk()
            ->assertViewHas('upcomingBookings', function ($rows) use ($references) {
                parse_str(parse_url($rows->nextPageUrl(), PHP_URL_QUERY), $query);

                return $rows->total() === 12 && $rows->count() === 10
                    && $rows->pluck('reference')->all() === array_slice($references, 0, 10)
                    && $query['range'] === 'today' && $query['per_page'] === '10';
            });
        $this->get(route('dashboard', ['range' => 'today', 'per_page' => 10, 'page' => 2]))->assertOk()
            ->assertViewHas('upcomingBookings', fn ($rows) => $rows->pluck('reference')->all() === array_slice($references, 10));
        $this->get(route('dashboard', ['range' => 'today', 'per_page' => 25]))->assertOk()
            ->assertViewHas('upcomingBookings', fn ($rows) => $rows->count() === 12 && $rows->perPage() === 25);
    }

    public function test_booking_and_payment_statuses_render_independently_with_text_and_colour(): void
    {
        [, $organization] = $this->context();
        foreach (BookingStatus::cases() as $status) {
            $this->booking($organization, '2026-09-04 10:00', ['status' => $status]);
        }
        foreach (BookingPaymentStatus::cases() as $status) {
            $this->booking($organization, '2026-09-04 11:00', ['payment_status' => $status]);
        }
        $this->booking($organization, '2026-09-04 12:00', ['price_minor' => 0]);

        $response = $this->get(route('dashboard', ['per_page' => 25]))->assertOk();
        foreach (BookingStatus::cases() as $status) {
            $response->assertSee($status->label());
        }
        foreach (BookingPaymentStatus::cases() as $status) {
            $response->assertSee('class="badge '.$status->badgeClass().' text-wrap">'.$status->label().'</span>', false);
        }
        $response->assertSee('class="badge text-bg-success text-wrap">Confirmed</span>', false)
            ->assertSee('class="badge text-bg-warning text-wrap">To be confirmed</span>', false)
            ->assertSee('No payment required');
    }

    public function test_empty_results_and_invalid_filters_are_handled(): void
    {
        $this->context();
        $this->get(route('dashboard'))->assertOk()->assertSee('No upcoming bookings in this date range.');
        $this->getJson(route('dashboard', ['range' => 'yesterday', 'per_page' => 100000, 'page' => 0]))
            ->assertUnprocessable()->assertJsonValidationErrors(['range', 'per_page', 'page']);
        $this->getJson(route('dashboard', ['range' => ['all'], 'per_page' => ['10']]))
            ->assertUnprocessable()->assertJsonValidationErrors(['range', 'per_page']);
    }

    private function context(MembershipRole $role = MembershipRole::Owner): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['timezone' => 'America/Toronto']);
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => $role,
            'status' => MembershipStatus::Active,
        ]);
        $this->actingAs($user)->withSession(['active_organization_uuid' => $organization->uuid]);

        return [$user, $organization];
    }

    private function booking(Organization $organization, string|CarbonImmutable $start, array $attributes = [], ?string $end = null): Booking
    {
        $start = $start instanceof CarbonImmutable ? $start->utc() : CarbonImmutable::parse($start, $organization->timezone)->utc();
        $end = $end ? CarbonImmutable::parse($end, $organization->timezone)->utc() : $start->addHour();
        $type = AppointmentType::firstOrCreate([
            'organization_id' => $organization->getKey(),
            'slug' => 'dashboard-session',
        ], ['name' => 'Dashboard session']);
        $appointment = Appointment::create([
            'organization_id' => $organization->getKey(), 'appointment_type_id' => $type->getKey(),
            'starts_at_utc' => $start, 'ends_at_utc' => $end,
            'blocked_starts_at_utc' => $start, 'blocked_ends_at_utc' => $end,
            'scheduling_timezone' => $organization->timezone, 'duration_value' => 60,
            'capacity' => 1, 'status' => 'scheduled',
        ]);
        $contact = OrganizationContact::firstOrCreate([
            'organization_id' => $organization->getKey(), 'email' => 'dashboard@example.test',
        ], ['first_name' => 'Dashboard', 'last_name' => 'Client']);

        return Booking::create(array_merge([
            'organization_id' => $organization->getKey(), 'appointment_id' => $appointment->getKey(),
            'appointment_type_id' => $type->getKey(), 'organization_contact_id' => $contact->getKey(),
            'reference' => sprintf('DASH%08d', ++$this->referenceSequence),
            'status' => BookingStatus::Confirmed, 'payment_status' => BookingPaymentStatus::Unpaid,
            'price_minor' => 10000, 'paid_minor' => 0, 'refunded_minor' => 0,
            'booking_timezone' => 'Pacific/Auckland', 'currency' => 'CAD', 'attendee_count' => 1,
            'first_name' => 'Dashboard', 'last_name' => 'Client', 'email' => 'dashboard@example.test',
            'email_normalized' => 'dashboard@example.test', 'manage_token_hash' => random_bytes(32),
        ], $attributes));
    }
}
