<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationContact;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationContactFactory extends Factory
{
    protected $model = OrganizationContact::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
        ];
    }
}
