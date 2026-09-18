<?php

namespace Tests\Feature;

use App\Domain\Money\PaymentCurrencyCatalog;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_organization_uses_supported_currency_dropdown(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['currency' => 'CAD']);

        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->get(route('organizations.edit', $organization));

        $response->assertOk()
            ->assertSee('<select id="currency" name="currency" required>', false)
            ->assertSee('CAD — Canadian Dollar', false)
            ->assertSee('MXN — Mexican Peso', false)
            ->assertSee('USD — United States Dollar', false);

        $this->assertMatchesRegularExpression(
            '/<option\b[^>]*\bvalue="CAD"[^>]*\bselected(?:="selected")?[^>]*>\s*CAD\s+—\s+Canadian Dollar\s*<\/option>/u',
            $response->getContent(),
            'The organization currency dropdown should select the organization\'s current currency.'
        );
    }

    public function test_organization_rejects_currency_not_supported_by_both_payment_providers(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->put(route('organizations.update', $organization), [
                'name' => $organization->name,
                'timezone' => 'America/Toronto',
                'currency' => 'ZAR',
            ]);

        $response->assertSessionHasErrors('currency');
        $this->assertNotSame('ZAR', $organization->fresh()->currency);
    }

    public function test_catalog_is_the_expected_common_provider_currency_set(): void
    {
        $this->assertSame([
            'AUD', 'BRL', 'CAD', 'CNY', 'CZK', 'DKK', 'EUR', 'HKD',
            'HUF', 'ILS', 'JPY', 'MYR', 'MXN', 'TWD', 'NZD', 'NOK',
            'PHP', 'PLN', 'GBP', 'SGD', 'SEK', 'CHF', 'THB', 'USD',
        ], PaymentCurrencyCatalog::codes());
    }
}
