<?php

namespace App\Filament\Resources\KimCards;

use App\Filament\Resources\KimCards\Pages\ListKimCards;
use App\Filament\Resources\KimCards\Pages\ViewKimCard;
use App\Filament\Resources\KimCards\Schemas\KimCardInfolist;
use App\Filament\Resources\KimCards\Tables\KimCardsTable;
use App\Models\KimCard;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class KimCardResource extends Resource
{
    protected static ?string $model = KimCard::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $navigationLabel = 'KIM Terbit';

    protected static ?string $modelLabel = 'KIM Terbit';

    protected static ?string $pluralModelLabel = 'KIM Terbit';

    protected static string|UnitEnum|null $navigationGroup = 'Operasional';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'kim_number';

    public static function canAccess(): bool
    {
        return auth()->user()->can('view_kim_cards');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['kim_number', 'application.application_number', 'application.vehicle.registration_number'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'NOPOL' => $record->application?->vehicle?->registration_number ?? '—',
            'Perusahaan' => $record->application?->transporter?->company_name ?? '—',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['application.vehicle', 'application.transporter']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return KimCardInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KimCardsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKimCards::route('/'),
            'view' => ViewKimCard::route('/{record}'),
        ];
    }
}
