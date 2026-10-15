<?php

namespace App\Filament\Resources\KimApplications;

use App\Filament\Resources\KimApplications\Tables\KimApplicationsTable;
use App\Models\KimApplication;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class KimApplicationResource extends Resource
{
    protected static ?string $model = KimApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Pengajuan & Pemeriksaan';

    protected static ?string $modelLabel = 'Pengajuan KIM';

    protected static ?int $navigationSort = 8;

    public static function canAccess(): bool
    {
        return auth()->user()->can('view_applications');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return KimApplicationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKimApplications::route('/'),
            'view' => Pages\ViewKimApplication::route('/{record}'),
            'inspect' => Pages\InspectKimApplication::route('/{record}/inspect'),
        ];
    }
}
