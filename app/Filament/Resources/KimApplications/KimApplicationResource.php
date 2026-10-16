<?php

namespace App\Filament\Resources\KimApplications;

use App\Filament\Resources\KimApplications\Tables\KimApplicationsTable;
use App\Models\KimApplication;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class KimApplicationResource extends Resource
{
    protected static ?string $model = KimApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Pengajuan & Pemeriksaan';

    protected static ?string $modelLabel = 'Pengajuan KIM';

    protected static ?int $navigationSort = 8;

    protected static ?string $recordTitleAttribute = 'application_number';

    public static function getGloballySearchableAttributes(): array
    {
        return ['application_number', 'vehicle.registration_number', 'transporter.company_name'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'NOPOL' => $record->vehicle?->registration_number ?? '—',
            'Perusahaan' => $record->transporter?->company_name ?? '—',
            'Status' => $record->status->label(),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['vehicle', 'transporter']);
    }

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
