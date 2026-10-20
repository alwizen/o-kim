<?php

namespace App\Filament\Transportir\Resources\KimCards;

use App\Filament\Transportir\Resources\KimCards\Pages\ListKimCards;
use App\Filament\Transportir\Resources\KimCards\Pages\ViewKimCard;
use App\Filament\Transportir\Resources\KimCards\Schemas\KimCardInfolist;
use App\Filament\Transportir\Resources\KimCards\Tables\KimCardsTable;
use App\Models\KimCard;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class KimCardResource extends Resource
{
    protected static ?string $model = KimCard::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $navigationLabel = 'KIM Terbit';

    protected static ?string $modelLabel = 'KIM Terbit';

    protected static ?string $pluralModelLabel = 'KIM Terbit';

    protected static ?int $navigationSort = 12;

    protected static ?string $recordTitleAttribute = 'kim_number';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('transportir') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canView(Model $record): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->transporters()
            ->where('transporters.id', $record->application?->transporter_id)
            ->exists();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['kim_number', 'application.vehicle.registration_number'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'NOPOL' => $record->application?->vehicle?->registration_number ?? '—',
            'Berlaku Hingga' => $record->expires_at?->format('d M Y') ?? '—',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('application.vehicle');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('application', fn (Builder $query) => $query
                ->whereIn('transporter_id', auth()->user()->transporters()->pluck('transporters.id')))
            ->with(['application.vehicle', 'application.transporter']);
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
