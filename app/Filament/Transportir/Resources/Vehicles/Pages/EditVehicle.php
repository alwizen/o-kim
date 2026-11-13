<?php

namespace App\Filament\Transportir\Resources\Vehicles\Pages;

use App\Filament\Transportir\Resources\Vehicles\VehicleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVehicle extends EditRecord
{
    protected static string $resource = VehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->disabled()
                ->tooltip('Penghapusan kendaraan tidak tersedia. Hubungi admin untuk menonaktifkan kendaraan.'),
        ];
    }
}
