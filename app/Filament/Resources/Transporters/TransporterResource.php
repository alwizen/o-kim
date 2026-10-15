<?php

namespace App\Filament\Resources\Transporters;

use App\Filament\Resources\Transporters\Pages\ListTransporters;
use App\Filament\Resources\Transporters\Pages\ViewTransporter;
use App\Filament\Resources\Transporters\Schemas\TransporterInfolist;
use App\Filament\Resources\Transporters\Tables\TransportersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TransporterResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static ?string $navigationLabel = 'Verifikasi Transportir';

    protected static ?string $modelLabel = 'Pendaftaran Transportir';

    protected static ?string $pluralModelLabel = 'Pendaftaran Transportir';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->user()->can('review_transporter_registrations');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->can('review_transporter_registrations');
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()->can('review_transporter_registrations')
            && $record->hasRole('transportir');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->role('transportir');
    }

    public static function infolist(Schema $schema): Schema
    {
        return TransporterInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TransportersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransporters::route('/'),
            'view' => ViewTransporter::route('/{record}'),
        ];
    }
}
