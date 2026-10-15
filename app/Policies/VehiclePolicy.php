<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('transportir') || $user->can('access_admin_panel');
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        if ($user->can('access_admin_panel')) {
            return true;
        }

        return $this->ownsVehicle($user, $vehicle);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('transportir');
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $this->ownsVehicle($user, $vehicle);
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $this->ownsVehicle($user, $vehicle);
    }

    private function ownsVehicle(User $user, Vehicle $vehicle): bool
    {
        return $user->transporters()
            ->where('transporters.id', $vehicle->transporter_id)
            ->exists();
    }
}
