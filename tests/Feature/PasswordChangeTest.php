<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_or_submit_a_password_change(): void
    {
        $this->get(route('account.password.edit'))->assertRedirect(route('login'));
        $this->put(route('account.password.update'), [
            'current_password' => 'Password12345',
            'password' => 'Replacement123',
            'password_confirmation' => 'Replacement123',
        ])->assertRedirect(route('login'));
    }

    public function test_an_account_without_an_organization_can_view_the_password_form(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $this->actingAs($user)->get(route('account.password.edit'))
            ->assertOk()
            ->assertSee('Current password')
            ->assertSee('At least 12 characters')
            ->assertSee('Symbols (such as ! @ # $) are optional; none are required.')
            ->assertSee('js/password-checklist.js');
    }

    public function test_a_password_change_is_hashed_and_only_updates_the_signed_in_account(): void
    {
        $user = User::factory()->create(['remember_token' => 'previous-token']);
        $other = User::factory()->create();
        $originalOtherHash = $other->password;

        $this->actingAs($user)->put(route('account.password.update'), [
            'user_id' => $other->uuid,
            'current_password' => 'Password12345',
            'password' => 'Replacement123',
            'password_confirmation' => 'Replacement123',
        ])->assertRedirect(route('account.password.edit'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Your password has been changed.');

        $user->refresh();
        $this->assertTrue(Hash::check('Replacement123', $user->password));
        $this->assertFalse(Hash::check('Password12345', $user->password));
        $this->assertNotSame('previous-token', $user->remember_token);
        $this->assertSame($originalOtherHash, $other->fresh()->password);
        $this->assertAuthenticatedAs($user);
    }

    #[DataProvider('invalidChanges')]
    public function test_invalid_changes_preserve_the_password_and_do_not_flash_secrets(
        string $current,
        string $password,
        string $confirmation,
        string $error,
    ): void {
        $user = User::factory()->create();
        $originalHash = $user->password;

        $this->actingAs($user)->from(route('account.password.edit'))
            ->put(route('account.password.update'), [
                'current_password' => $current,
                'password' => $password,
                'password_confirmation' => $confirmation,
            ])->assertRedirect(route('account.password.edit'))
            ->assertSessionHasErrors($error)
            ->assertSessionMissing('_old_input.current_password')
            ->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_confirmation');

        $this->assertSame($originalHash, $user->fresh()->password);
    }

    public static function invalidChanges(): array
    {
        return [
            'wrong current password' => ['WrongPassword123', 'Replacement123', 'Replacement123', 'current_password'],
            'missing current password' => ['', 'Replacement123', 'Replacement123', 'current_password'],
            'too short' => ['Password12345', 'Short123', 'Short123', 'password'],
            'missing lowercase' => ['Password12345', 'UPPERCASE12345', 'UPPERCASE12345', 'password'],
            'missing uppercase' => ['Password12345', 'lowercase12345', 'lowercase12345', 'password'],
            'missing number' => ['Password12345', 'NoNumbersHere!', 'NoNumbersHere!', 'password'],
            'mismatched confirmation' => ['Password12345', 'Replacement123', 'Replacement456', 'password'],
        ];
    }

    public function test_password_changes_are_rate_limited(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->put(route('account.password.update'), [
                'current_password' => 'WrongPassword123',
                'password' => 'Replacement123',
                'password_confirmation' => 'Replacement123',
            ])->assertSessionHasErrors('current_password');
        }

        $this->put(route('account.password.update'))->assertTooManyRequests();
        $this->assertTrue(Hash::check('Password12345', $user->fresh()->password));
    }
}
