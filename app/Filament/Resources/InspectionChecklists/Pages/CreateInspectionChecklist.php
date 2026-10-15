<?php

namespace App\Filament\Resources\InspectionChecklists\Pages;

use App\Filament\Resources\InspectionChecklists\InspectionChecklistResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInspectionChecklist extends CreateRecord
{
    protected static string $resource = InspectionChecklistResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
