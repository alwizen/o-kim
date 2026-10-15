<?php

namespace Database\Factories;

use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentType>
 */
class DocumentTypeFactory extends Factory
{
    protected $model = DocumentType::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('DOC-####')),
            'name' => fake()->word(),
            'is_required' => false,
            'has_expiry_date' => true,
            'is_active' => true,
        ];
    }
}
