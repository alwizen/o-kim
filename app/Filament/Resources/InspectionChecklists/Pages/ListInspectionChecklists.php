<?php

namespace App\Filament\Resources\InspectionChecklists\Pages;

use App\Filament\Resources\InspectionChecklists\InspectionChecklistResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInspectionChecklists extends ListRecords
{
    protected static string $resource = InspectionChecklistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
