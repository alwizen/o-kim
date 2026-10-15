<?php

namespace App\Filament\Transportir\Resources\KimApplications;

use App\Filament\Transportir\Resources\KimApplications\Pages\CreateKimApplication;
use App\Filament\Transportir\Resources\KimApplications\Pages\ListKimApplications;
use App\Filament\Transportir\Resources\KimApplications\Pages\ViewKimApplication;
use App\Filament\Transportir\Resources\KimApplications\Tables\KimApplicationsTable;
use App\Models\KimApplication;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class KimApplicationResource extends Resource
{
    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('transportir') ?? false;
    }

    protected static ?string $model = KimApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentPlus;

    protected static ?string $navigationLabel = 'Pengajuan KIM';

    protected static ?string $modelLabel = 'Pengajuan KIM';

    protected static ?int $navigationSort = 5;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('transporter_id', auth()->user()->transporters()->pluck('transporters.id'));
    }

    public static function table(Table $table): Table
    {
        return KimApplicationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKimApplications::route('/'),
            'create' => CreateKimApplication::route('/create'),
            'view' => ViewKimApplication::route('/{record}'),
        ];
    }
}
