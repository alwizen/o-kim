<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Models\KimApplication;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KimApplication>
 */
class KimApplicationFactory extends Factory
{
    protected $model = KimApplication::class;

    public function definition(): array
    {
        return [
            'application_number' => 'APP-'.now()->format('Ymd').'-'.fake()->unique()->numerify('####'),
            'vehicle_id' => Vehicle::factory(),
            'transporter_id' => fn (array $attributes) => Vehicle::find($attributes['vehicle_id'])->transporter_id,
            'application_type' => ApplicationType::New,
            'status' => ApplicationStatus::Draft,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status' => ApplicationStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }
}
