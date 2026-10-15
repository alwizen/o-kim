<?php

namespace Database\Factories;

use App\Models\Transporter;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'transporter_id' => Transporter::factory(),
            'registration_number' => strtoupper(fake()->bothify('? #### ???')),
            'brand' => fake()->randomElement(['Hino', 'Mitsubishi', 'Isuzu']),
            'model' => fake()->word(),
            'manufacture_year' => fake()->numberBetween(2015, 2024),
            'tank_capacity' => fake()->randomElement([8000, 16000, 24000, 32000]),
        ];
    }
}
