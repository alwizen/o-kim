<?php

namespace App\Models;

use App\Enums\VehicleStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

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
        'karoseri_builder',
        'karoseri_built_period',
        'tank_wall_thickness_mm',
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

        // Kendaraan yang punya riwayat pengajuan/KIM tidak boleh dihapus agar
        // dokumen terbit tetap dapat diaudit. Nonaktifkan saja bila tidak dipakai.
        static::deleting(function (Vehicle $vehicle) {
            if ($vehicle->kimApplications()->exists()) {
                throw ValidationException::withMessages([
                    'vehicle' => 'Kendaraan yang memiliki riwayat pengajuan/KIM tidak dapat dihapus. Nonaktifkan kendaraan sebagai gantinya.',
                ]);
            }
        });
    }

    public static function normalizeRegistrationNumber(string $value): string
    {
        return strtoupper((string) preg_replace('/\s+/', ' ', trim($value)));
    }

    /**
     * NOPOL sebagai nama folder penyimpanan berkas.
     *
     * NOPOL bisa mengandung spasi/titik/strip, sehingga dinormalisasi menjadi
     * hanya huruf dan angka (mis. "G 1234 AB" -> "G1234AB") agar aman dipakai
     * pada path object storage maupun S3.
     */
    public function storageFolder(): string
    {
        $folder = (string) preg_replace('/[^A-Za-z0-9]/', '', (string) $this->registration_number);

        // Fallback agar tidak pernah menghasilkan folder kosong.
        return $folder !== '' ? strtoupper($folder) : 'vehicle-'.$this->id;
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
