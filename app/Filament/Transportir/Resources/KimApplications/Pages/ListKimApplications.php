<?php

namespace App\Filament\Transportir\Resources\KimApplications\Pages;

use App\Filament\Transportir\Resources\KimApplications\KimApplicationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKimApplications extends ListRecords
{
    protected static string $resource = KimApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
