<?php

namespace App\Filament\Resources\InspectionChecklists\Pages;

use App\Filament\Resources\InspectionChecklists\InspectionChecklistResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInspectionChecklist extends EditRecord
{
    protected static string $resource = InspectionChecklistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
