<?php

namespace App\Filament\Transportir\Resources\Vehicles\Pages;

use App\Filament\Transportir\Resources\Vehicles\VehicleResource;
use App\Notifications\VehicleCreated;
use App\Support\AppNotifier;
use Filament\Resources\Pages\CreateRecord;

class CreateVehicle extends CreateRecord
{
    protected static string $resource = VehicleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['transporter_id'] = auth()->user()->transporters()->value('transporters.id');

        return $data;
    }

    protected function afterCreate(): void
    {
        $vehicle = $this->getRecord();
        $company = $vehicle->transporter?->company_name ?? '-';

        AppNotifier::notifyStaffWithPermission(
            'verify_documents',
            new VehicleCreated(
                $company,
                (string) $vehicle->registration_number,
                (string) $vehicle->getRouteKey(),
            ),
        );
    }
}
