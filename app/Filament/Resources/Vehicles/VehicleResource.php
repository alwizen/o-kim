<?php

namespace App\Filament\Resources\Vehicles;

use App\Filament\Resources\Vehicles\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\Vehicles\Tables\VehiclesTable;
use App\Models\Vehicle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class VehicleResource extends Resource
{
    protected static ?string $model = Vehicle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $navigationLabel = 'Kendaraan';

    protected static ?string $modelLabel = 'Kendaraan';

    protected static ?string $pluralModelLabel = 'Kendaraan';

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'registration_number';

    public static function getGloballySearchableAttributes(): array
    {
        return ['registration_number', 'chassis_number', 'engine_number', 'brand', 'model', 'transport_type'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Perusahaan' => $record->transporter?->company_name ?? '—',
            'Merek' => $record->brand ?? '—',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('transporter');
    }

    public static function canAccess(): bool
    {
        // Petugas pengelola kendaraan atau petugas verifikasi dokumen.
        return auth()->user()->can('manage_vehicles')
            || auth()->user()->can('verify_documents');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return VehiclesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVehicles::route('/'),
            'view' => Pages\ViewVehicle::route('/{record}'),
        ];
    }
}
