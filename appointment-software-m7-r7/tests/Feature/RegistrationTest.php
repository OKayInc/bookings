<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_person_user_and_first_organization(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'first_name' => 'Luis',
            'last_name' => 'Example',
            'email' => 'luis@example.test',
            'password' => 'StrongPassword123',
            'password_confirmation' => 'StrongPassword123',
            'timezone' => 'America/Toronto',
            'organization_name' => 'More Than Photos',
            'organization_timezone' => 'America/Toronto',
            'currency' => 'CAD',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $this->assertSame(1, Person::count());
        $this->assertSame(1, User::count());
        $this->assertSame(1, Organization::count());
        $membership = Person::first()->memberships()->first();
        $this->assertSame(MembershipRole::Owner, $membership->role);
        $this->assertAuthenticated();
        $user = User::firstOrFail();
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registration_page_uses_supported_currency_dropdown(): void
    {
        $response = $this->get('/register');

        $response->assertOk()
            ->assertSee('<select id="currency" name="currency" required>', false)
            ->assertSee('CAD — Canadian Dollar', false)
            ->assertSee('MXN — Mexican Peso', false)
            ->assertSee('USD — United States Dollar', false);

        $this->assertMatchesRegularExpression(
            '/<option\b[^>]*\bvalue="CAD"[^>]*\bselected(?:="selected")?[^>]*>\s*CAD\s+—\s+Canadian Dollar\s*<\/option>/u',
            $response->getContent(),
            'The registration currency dropdown should select CAD by default.'
        );
    }
}
