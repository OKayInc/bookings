<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PersonFactory extends Factory
{
    protected $model = \App\Models\Person::class;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'primary_email' => fake()->unique()->safeEmail(),
            'timezone' => 'America/Toronto',
            'locale' => 'en',
        ];
    }
}
