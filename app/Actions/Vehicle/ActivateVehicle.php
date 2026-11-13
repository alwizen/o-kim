<?php

namespace App\Actions\Vehicle;

use App\Enums\VehicleStatus;
use App\Models\Vehicle;
use App\Services\Audit\ActivityLogger;

class ActivateVehicle
{
    /**
     * Aktifkan kembali kendaraan (Mobil Tangki).
     */
    public function execute(Vehicle $vehicle): void
    {
        $vehicle->update([
            'status' => VehicleStatus::Active,
        ]);

        app(ActivityLogger::class)->log(
            'vehicle.activated',
            $vehicle,
            'Kendaraan '.$vehicle->registration_number.' diaktifkan.',
            [],
            label: $vehicle->registration_number,
        );
    }
}
