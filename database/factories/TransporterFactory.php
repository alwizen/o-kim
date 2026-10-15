<?php

namespace Database\Factories;

use App\Models\Transporter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transporter>
 */
class TransporterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_name' => fake()->company(),
            'company_address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'legal_identifier' => fake()->numerify('NIB-########'),
        ];
    }
}
