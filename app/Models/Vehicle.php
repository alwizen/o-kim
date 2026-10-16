<?php

namespace App\Models;

use App\Enums\VehicleStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transporter_id',
        'registration_number',
        'brand',
        'model',
        'manufacture_year',
        'tank_capacity',
        'chassis_number',
        'engine_number',
        'transport_type',
        'tank_material',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => VehicleStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Vehicle $vehicle) {
            $vehicle->registration_number = static::normalizeRegistrationNumber(
                $vehicle->registration_number
            );
        });
    }

    public static function normalizeRegistrationNumber(string $value): string
    {
        return strtoupper((string) preg_replace('/\s+/', ' ', trim($value)));
    }

    public function transporter(): BelongsTo
    {
        return $this->belongsTo(Transporter::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VehicleDocument::class);
    }

    public function kimApplications(): HasMany
    {
        return $this->hasMany(KimApplication::class);
    }
}
