<?php

namespace App\Filament\Transportir\Resources\Findings\Pages;

use App\Filament\Transportir\Resources\Findings\FindingResource;
use Filament\Resources\Pages\ListRecords;

class ListFindings extends ListRecords
{
    protected static string $resource = FindingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
