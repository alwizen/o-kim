<?php

namespace App\Filament\Resources\KimApplications\Pages;

use App\Filament\Resources\KimApplications\KimApplicationResource;
use Filament\Resources\Pages\ListRecords;

class ListKimApplications extends ListRecords
{
    protected static string $resource = KimApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
