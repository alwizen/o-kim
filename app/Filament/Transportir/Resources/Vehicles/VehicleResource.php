<?php

namespace App\Filament\Transportir\Resources\Vehicles;

use App\Filament\Transportir\Resources\Vehicles\Pages\CreateVehicle;
use App\Filament\Transportir\Resources\Vehicles\Pages\EditVehicle;
use App\Filament\Transportir\Resources\Vehicles\Pages\ListVehicles;
use App\Filament\Transportir\Resources\Vehicles\Schemas\VehicleForm;
use App\Filament\Transportir\Resources\Vehicles\Tables\VehiclesTable;
use App\Models\Vehicle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class VehicleResource extends Resource
{
    protected static ?string $recordTitleAttribute = 'registration_number';

    public static function getGloballySearchableAttributes(): array
    {
        return ['registration_number', 'chassis_number', 'engine_number', 'brand', 'model'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return ['Merek' => $record->brand ?? '—', 'Tahun' => $record->manufacture_year ?? '—'];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('transportir') ?? false;
    }

    protected static ?string $model = Vehicle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $navigationLabel = 'Kendaraan';

    protected static ?string $modelLabel = 'Kendaraan';

    protected static ?string $pluralModelLabel = 'Kendaraan';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('transporter_id', auth()->user()->transporters()->pluck('transporters.id'));
    }

    public static function form(Schema $schema): Schema
    {
        return VehicleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VehiclesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicles::route('/'),
            'create' => CreateVehicle::route('/create'),
            'edit' => EditVehicle::route('/{record}/edit'),
        ];
    }
}
