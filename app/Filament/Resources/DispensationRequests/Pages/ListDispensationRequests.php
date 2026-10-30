<?php

namespace App\Filament\Resources\DispensationRequests\Pages;

use App\Filament\Resources\DispensationRequests\DispensationRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListDispensationRequests extends ListRecords
{
    protected static string $resource = DispensationRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
