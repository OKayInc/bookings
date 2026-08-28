<?php

namespace Database\Factories;

use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected $model = \App\Models\User::class;
    protected static ?string $password;

    public function definition(): array
    {
        $person = Person::factory()->create();
        return [
            'person_id' => $person->getKey(),
            'email' => $person->primary_email,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('Password12345'),
            'remember_token' => null,
        ];
    }
}
