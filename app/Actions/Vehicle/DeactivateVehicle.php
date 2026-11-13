<?php

namespace App\Actions\Vehicle;

use App\Enums\VehicleStatus;
use App\Models\Vehicle;
use App\Services\Audit\ActivityLogger;

class DeactivateVehicle
{
    /**
     * Nonaktifkan kendaraan (Mobil Tangki) tanpa menghapus datanya.
     */
    public function execute(Vehicle $vehicle): void
    {
        $vehicle->update([
            'status' => VehicleStatus::Inactive,
        ]);

        app(ActivityLogger::class)->log(
            'vehicle.deactivated',
            $vehicle,
            'Kendaraan '.$vehicle->registration_number.' dinonaktifkan.',
            [],
            label: $vehicle->registration_number,
        );
    }
}
