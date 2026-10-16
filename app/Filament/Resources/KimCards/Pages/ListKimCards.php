<?php

namespace App\Filament\Resources\KimCards\Pages;

use App\Filament\Resources\KimCards\KimCardResource;
use Filament\Resources\Pages\ListRecords;

class ListKimCards extends ListRecords
{
    protected static string $resource = KimCardResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
