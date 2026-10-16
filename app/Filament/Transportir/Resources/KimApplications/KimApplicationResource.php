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
use Illuminate\Database\Eloquent\Model;

class KimApplicationResource extends Resource
{
    protected static ?string $recordTitleAttribute = 'application_number';

    public static function getGloballySearchableAttributes(): array
    {
        return ['application_number', 'vehicle.registration_number'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return ['NOPOL' => $record->vehicle?->registration_number ?? '—', 'Status' => $record->status->label()];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('vehicle');
    }

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
