<?php

namespace App\Filament\Resources\DocumentScanLogs\Pages;

use App\Filament\Resources\DocumentScanLogs\DocumentScanLogResource;
use Filament\Resources\Pages\ListRecords;

class ListDocumentScanLogs extends ListRecords
{
    protected static string $resource = DocumentScanLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
