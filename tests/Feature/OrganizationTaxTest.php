<?php

namespace Tests\Feature;

use App\Domain\Availability\AvailabilityScheduleService;
use App\Domain\Questionnaires\QuestionnairePriceLine;
use App\Domain\Questionnaires\QuestionnaireQuote;
use App\Domain\Taxes\OrganizationTaxService;
use App\Domain\Taxes\TaxRate;
use App\Enums\AvailabilityScope;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\TaxPriceMode;
use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class OrganizationTaxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 14:00:00', 'UTC'));
        Cache::flush();
        Notification::fake();
        config(['questionnaire.email_dns_validation' => false]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_owner_can_configure_multiple_precise_taxes_and_disable_them(): void
    {
        [$user, $organization] = $this->ownedOrganization();

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->get(route('organizations.edit', $organization))
            ->assertOk()
            ->assertSee('name="collects_taxes"', false)
            ->assertSee('name="tax_identifier"', false)
            ->assertSee('name="tax_price_mode"', false)
            ->assertSee('id="add-organization-tax"', false);

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->put(route('organizations.update', $organization), [
                ...$this->organizationPayload($organization),
                'collects_taxes' => '1',
                'tax_identifier' => 'GST/QST 1234 5678',
                'tax_price_mode' => 'exclusive',
                'taxes' => [
                    ['name' => 'GST', 'percentage' => '5'],
                    ['name' => 'QST', 'percentage' => '9.975'],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('organizations.index'));

        $organization->refresh()->load('taxes');
        $this->assertTrue($organization->collects_taxes);
        $this->assertSame(TaxPriceMode::Exclusive, $organization->tax_price_mode);
        $this->assertSame('GST/QST 1234 5678', $organization->tax_identifier);
        $this->assertSame(['GST', 'QST'], $organization->taxes->pluck('name')->all());
        $this->assertSame([50_000, 99_750], $organization->taxes->pluck('rate_millionths')->all());

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->get(route('organizations.edit', $organization))
            ->assertOk()
            ->assertSee('value="9.975"', false)
            ->assertSee('GST/QST 1234 5678');

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->put(route('organizations.update', $organization), [
                ...$this->organizationPayload($organization),
                'collects_taxes' => '0',
                'taxes' => [['name' => 'Forged tax', 'percentage' => '99']],
            ])
            ->assertSessionHasNoErrors();

        $organization->refresh();
        $this->assertFalse($organization->collects_taxes);
        $this->assertNull($organization->tax_identifier);
        $this->assertNull($organization->tax_price_mode);
        $this->assertDatabaseCount('organization_taxes', 0);
    }

    public function test_tax_configuration_is_validated_on_the_server(): void
    {
        [$user, $organization] = $this->ownedOrganization();
        $this->actingAs($user)->withSession(['active_organization_uuid' => $organization->uuid]);

        $this->put(route('organizations.update', $organization), [
            ...$this->organizationPayload($organization),
            'collects_taxes' => '1',
        ])->assertSessionHasErrors(['tax_identifier', 'tax_price_mode', 'taxes']);

        $this->put(route('organizations.update', $organization), [
            ...$this->organizationPayload($organization),
            'collects_taxes' => '1',
            'tax_identifier' => '123456789',
            'tax_price_mode' => 'inclusive',
            'taxes' => [
                ['name' => 'VAT', 'percentage' => '20'],
                ['name' => 'vat', 'percentage' => '101'],
            ],
        ])->assertSessionHasErrors(['taxes.1.name', 'taxes.1.percentage']);

        $organization->refresh();
        $this->assertFalse($organization->collects_taxes);
        $this->assertDatabaseCount('organization_taxes', 0);
    }

    public function test_exclusive_taxes_are_added_to_checkout_and_snapshotted_on_the_booking(): void
    {
        [$organization, $type] = $this->taxableAppointment('exclusive', 10_000);
        [$token, $slots] = $this->hold($type);

        $slots->assertJsonPath('subtotal_minor', 10_000)
            ->assertJsonPath('tax_total_minor', 1_200)
            ->assertJsonPath('price_minor', 11_200);

        $this->get(route('public.booking-holds.edit', $token))
            ->assertOk()
            ->assertSee('id="questionnaire-price-card"', false)
            ->assertSee('id="questionnaire-tax-id"', false);

        $this->postJson(route('public.booking-holds.quote', $token))
            ->assertOk()
            ->assertJsonPath('subtotal_minor', 10_000)
            ->assertJsonPath('taxes.0.name', 'GST')
            ->assertJsonPath('taxes.0.amount_minor', 500)
            ->assertJsonPath('taxes.1.name', 'PST')
            ->assertJsonPath('taxes.1.amount_minor', 700)
            ->assertJsonPath('tax_total_minor', 1_200)
            ->assertJsonPath('total_minor', 11_200)
            ->assertJsonPath('tax_price_mode', 'exclusive')
            ->assertJsonPath('tax_identifier', 'BN 123456789 RT0001');

        $response = $this->post(route('public.booking-holds.store', $token), [
            'first_name' => 'Tax',
            'last_name' => 'Client',
            'email' => 'exclusive-tax@example.test',
        ]);
        $booking = Booking::query()->with(['priceLines', 'taxLines'])->firstOrFail();
        $response->assertRedirect(route('public.bookings.received', $booking->reference));

        $this->assertSame(10_000, $booking->subtotal_minor);
        $this->assertSame(1_200, $booking->tax_total_minor);
        $this->assertSame(11_200, $booking->price_minor);
        $this->assertSame(TaxPriceMode::Exclusive, $booking->tax_price_mode);
        $this->assertSame('BN 123456789 RT0001', $booking->tax_identifier);
        $this->assertSame([500, 700], $booking->taxLines->pluck('amount_minor')->all());
        $this->assertSame(11_200, $booking->initial_payment_due_minor);

        $organization->update([
            'collects_taxes' => false,
            'tax_identifier' => null,
            'tax_price_mode' => null,
        ]);
        $organization->taxes()->delete();

        $booking->refresh()->load(['priceLines', 'taxLines']);
        $this->view('public.bookings.partials.questionnaire-answers', [
            'booking' => $booking,
            'manageToken' => 'token',
        ])->assertSee('Subtotal before tax')
            ->assertSee('GST (5%, added)')
            ->assertSee('PST (7%, added)')
            ->assertSee('Total tax')
            ->assertSee('Tax ID: BN 123456789 RT0001');
    }

    public function test_inclusive_taxes_are_extracted_without_changing_the_advertised_total(): void
    {
        [, $type] = $this->taxableAppointment('inclusive', 11_200);
        [$token, $slots] = $this->hold($type);

        $slots->assertJsonPath('subtotal_minor', 10_000)
            ->assertJsonPath('tax_total_minor', 1_200)
            ->assertJsonPath('price_minor', 11_200);

        $this->postJson(route('public.booking-holds.quote', $token))
            ->assertOk()
            ->assertJsonPath('subtotal_minor', 10_000)
            ->assertJsonPath('taxes.0.amount_minor', 500)
            ->assertJsonPath('taxes.1.amount_minor', 700)
            ->assertJsonPath('tax_total_minor', 1_200)
            ->assertJsonPath('total_minor', 11_200)
            ->assertJsonPath('tax_price_mode', 'inclusive');

        $this->post(route('public.booking-holds.store', $token), [
            'first_name' => 'Included',
            'last_name' => 'Tax',
            'email' => 'included-tax@example.test',
        ])->assertSessionHasNoErrors();

        $booking = Booking::query()->with('taxLines')->firstOrFail();
        $this->assertSame(10_000, $booking->subtotal_minor);
        $this->assertSame(1_200, $booking->tax_total_minor);
        $this->assertSame(11_200, $booking->price_minor);
        $this->assertSame(TaxPriceMode::Inclusive, $booking->tax_price_mode);
        $this->assertSame([500, 700], $booking->taxLines->pluck('amount_minor')->all());
    }

    public function test_refundable_deposits_are_not_taxed_in_either_price_mode(): void
    {
        foreach (['exclusive', 'inclusive'] as $mode) {
            $organization = Organization::factory()->create([
                'collects_taxes' => true,
                'tax_identifier' => 'TAX-123',
                'tax_price_mode' => $mode,
            ]);
            $organization->taxes()->create(['name' => 'Combined tax', 'rate_millionths' => 120_000, 'position' => 1]);
            $serviceAmount = $mode === 'inclusive' ? 11_200 : 10_000;
            $quote = new QuestionnaireQuote($serviceAmount, $serviceAmount + 5_000, [
                new QuestionnairePriceLine('appointment_type', null, 'Service', 'base', '1', $serviceAmount),
                new QuestionnairePriceLine('resource_deposit', null, 'Refundable deposit', 'resource_deposit', '1', 5_000),
            ]);

            $taxQuote = app(OrganizationTaxService::class)->quote($organization, $quote);

            $this->assertSame(15_000, $taxQuote->subtotalMinor);
            $this->assertSame(1_200, $taxQuote->taxTotalMinor);
            $this->assertSame(16_200, $taxQuote->totalMinor);
        }
    }

    public function test_tax_rate_conversion_is_exact_to_four_decimal_places(): void
    {
        $this->assertSame(99_750, TaxRate::fromPercentage('9.975'));
        $this->assertSame('9.975', TaxRate::percentage(99_750));
        $this->assertSame(1, TaxRate::fromPercentage('0.0001'));
    }

    /** @return array{User,Organization} */
    private function ownedOrganization(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);

        return [$user, $organization];
    }

    /** @return array<string,string> */
    private function organizationPayload(Organization $organization): array
    {
        return [
            'name' => $organization->name,
            'timezone' => $organization->timezone,
            'currency' => $organization->currency,
        ];
    }

    /** @return array{Organization,AppointmentType} */
    private function taxableAppointment(string $mode, int $priceMinor): array
    {
        $organization = Organization::factory()->create([
            'slug' => 'tax-test-'.$mode,
            'timezone' => 'America/Toronto',
            'currency' => 'CAD',
            'collects_taxes' => true,
            'tax_identifier' => 'BN 123456789 RT0001',
            'tax_price_mode' => $mode,
        ]);
        $organization->taxes()->createMany([
            ['name' => 'GST', 'rate_millionths' => 50_000, 'position' => 1],
            ['name' => 'PST', 'rate_millionths' => 70_000, 'position' => 2],
        ]);
        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Taxed appointment',
            'slug' => 'taxed-appointment',
            'visibility' => 'public',
            'attendance_mode' => 'single',
            'capacity' => 1,
            'duration_mode' => 'fixed',
            'duration_unit' => 'minute',
            'duration_value' => 60,
            'start_interval_minutes' => 60,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'pricing_mode' => 'fixed',
            'fixed_price_minor' => $priceMinor,
            'email_verification_mode' => 'none',
            'is_active' => true,
        ]);
        app(AvailabilityScheduleService::class)->save(
            $organization,
            AvailabilityScope::Organization,
            $organization,
            'America/Toronto',
            true,
            [['weekday' => 1, 'start_time' => '09:00', 'end_time' => '12:00']],
        );

        return [$organization, $type->fresh('organization')];
    }

    /** @return array{string,TestResponse} */
    private function hold(AppointmentType $type): array
    {
        $slots = $this->getJson(route('public.booking.slots', $type).'?'.http_build_query([
            'access_mode' => 'direct',
            'timezone' => 'America/Toronto',
            'date' => '2026-08-31',
            'duration_value' => 60,
            'attendee_count' => 1,
        ]))->assertOk();
        $hold = $this->postJson(route('public.booking.holds.store', $type), [
            'access_mode' => 'direct',
            'timezone' => 'America/Toronto',
            'starts_at_utc' => $slots->json('slots.0.starts_at_utc'),
            'duration_value' => 60,
            'attendee_count' => 1,
        ])->assertOk();
        $token = basename((string) parse_url($hold->json('continue_url'), PHP_URL_PATH));

        return [$token, $slots];
    }
}
