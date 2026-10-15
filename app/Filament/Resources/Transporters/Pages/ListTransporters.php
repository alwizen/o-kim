<?php

namespace App\Filament\Resources\Transporters\Pages;

use App\Filament\Resources\Transporters\TransporterResource;
use Filament\Resources\Pages\ListRecords;

class ListTransporters extends ListRecords
{
    protected static string $resource = TransporterResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
