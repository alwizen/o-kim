<?php

namespace App\Filament\Resources\InspectionChecklists;

use App\Filament\Resources\InspectionChecklists\Schemas\InspectionChecklistForm;
use App\Filament\Resources\InspectionChecklists\Tables\InspectionChecklistsTable;
use App\Models\InspectionChecklist;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class InspectionChecklistResource extends Resource
{
    protected static ?string $model = InspectionChecklist::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Checklist Pemeriksaan';

    protected static ?string $modelLabel = 'Checklist';

    protected static ?string $pluralModelLabel = 'Checklist Pemeriksaan';

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 12;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'code'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return ['Kode' => $record->code, 'Fungsi' => $record->department->label()];
    }

    public static function canAccess(): bool
    {
        return auth()->user()->can('manage_checklists');
    }

    public static function form(Schema $schema): Schema
    {
        return InspectionChecklistForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InspectionChecklistsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInspectionChecklists::route('/'),
            'create' => Pages\CreateInspectionChecklist::route('/create'),
            'edit' => Pages\EditInspectionChecklist::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('items');
    }
}
