<?php

namespace App\Filament\Transportir\Resources\Findings;

use App\Filament\Transportir\Resources\Findings\Tables\FindingsTable;
use App\Models\Finding;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FindingResource extends Resource
{
    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('transportir') ?? false;
    }

    protected static ?string $model = Finding::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $navigationLabel = 'Temuan & Tindak Lanjut';

    protected static ?string $modelLabel = 'Temuan';

    protected static ?int $navigationSort = 7;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('application', fn (Builder $q) => $q->whereIn(
                'transporter_id',
                auth()->user()->transporters()->pluck('transporters.id')
            ));
    }

    public static function table(Table $table): Table
    {
        return FindingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFindings::route('/'),
            'view' => Pages\ViewFinding::route('/{record}'),
        ];
    }
}
