<?php

namespace App\Filament\Resources\Findings;

use App\Filament\Resources\Findings\Tables\FindingsTable;
use App\Models\Finding;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FindingResource extends Resource
{
    protected static ?string $model = Finding::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $navigationLabel = 'Temuan & Verifikasi';

    protected static ?string $modelLabel = 'Temuan';

    protected static ?int $navigationSort = 9;

    public static function canAccess(): bool
    {
        return auth()->user()->can('view_findings');
    }

    public static function canCreate(): bool
    {
        return false;
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
