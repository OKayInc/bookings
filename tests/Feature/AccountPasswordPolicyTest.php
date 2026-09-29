<?php

namespace Tests\Feature;

use App\Support\Auth\AccountPasswordPolicy;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountPasswordPolicyTest extends TestCase
{
    #[DataProvider('passwords')]
    public function test_account_password_validation_preserves_the_existing_policy(string $password, bool $accepted): void
    {
        $validator = Validator::make(['password' => $password], [
            'password' => ['required', AccountPasswordPolicy::rule()],
        ]);

        $this->assertSame($accepted, $validator->passes());
    }

    public static function passwords(): array
    {
        return [
            'no symbols needed' => ['Abcdefghijk1', true],
            'symbols allowed' => ['Abcdefghij1!', true],
            'Unicode letters and numbers' => ['Ééééééééééé١', true],
            'Unicode character count' => ['Aa1😀😀😀😀😀😀😀😀😀', true],
            'UTF-16 length is not character count' => ['Aa1😀😀😀😀😀', false],
            'too short' => ['Abcdefghij1', false],
            'no lowercase' => ['ABCDEFGHIJK1', false],
            'no uppercase' => ['abcdefghijk1', false],
            'no numbers' => ['Abcdefghijkl', false],
            'empty' => ['', false],
        ];
    }

    public function test_registration_renders_the_password_requirements_without_repopulating_passwords(): void
    {
        $this->withSession(['_old_input' => [
            'password' => 'NeverRedisplayThis123',
            'password_confirmation' => 'NeverRedisplayThis123',
        ]])->get(route('register'))->assertOk()
            ->assertSee('At least 12 characters')
            ->assertSee('At least 1 lowercase letter')
            ->assertSee('At least 1 uppercase letter')
            ->assertSee('At least 1 number')
            ->assertSee('Symbols (such as ! @ # $) are optional; none are required.')
            ->assertSee('Passwords match')
            ->assertSee('js/password-checklist.js')
            ->assertDontSee('NeverRedisplayThis123');
    }
}
