<?php

namespace App\Filament\Transportir\Resources\Vehicles\Pages;

use App\Filament\Transportir\Resources\Vehicles\VehicleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVehicle extends CreateRecord
{
    protected static string $resource = VehicleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['transporter_id'] = auth()->user()->transporters()->value('transporters.id');

        return $data;
    }
}
