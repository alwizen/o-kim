<?php

namespace Database\Factories;

use App\Enums\DocumentVerificationStatus;
use App\Models\DocumentType;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleDocument>
 */
class VehicleDocumentFactory extends Factory
{
    protected $model = VehicleDocument::class;

    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'document_type_id' => DocumentType::factory(),
            'document_number' => fake()->bothify('DOC-####'),
            'file_path' => 'vehicle-documents/1/'.fake()->uuid().'.pdf',
            'original_filename' => 'dokumen.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 102400,
            'issued_at' => now()->subYear()->toDateString(),
            'expires_at' => now()->addYear()->toDateString(),
            'verification_status' => DocumentVerificationStatus::Pending,
        ];
    }

    public function expiresIn(int $days): static
    {
        return $this->state(fn () => ['expires_at' => now()->addDays($days)->toDateString()]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDays(5)->toDateString()]);
    }
}
