<?php

namespace App\Filament\Resources\Roles;

use App\Enums\InternalRole;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;
use UnitEnum;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Role & Permission';

    protected static ?string $modelLabel = 'Role';

    protected static ?string $pluralModelLabel = 'Role & Permission';

    protected static string|UnitEnum|null $navigationGroup = 'User Manajemen';

    protected static ?int $navigationSort = 100;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('guard_name', 'web');
    }

    public static function canAccess(): bool
    {
        return auth()->user()->can('manage_roles');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can('manage_roles');
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();

        if (! $user->can('manage_roles')) {
            return false;
        }

        // Role super_admin hanya boleh diubah oleh super_admin.
        return $record->name !== InternalRole::SuperAdmin->value
            || $user->hasRole(InternalRole::SuperAdmin->value);
    }

    public static function canDelete($record): bool
    {
        // Role bawaan tidak boleh dihapus agar alur kerja tetap utuh.
        return auth()->user()->can('manage_roles')
            && ! in_array($record->name, InternalRole::values(), true)
            && $record->name !== 'transportir';
    }

    public static function form(Schema $schema): Schema
    {
        return Schemas\RoleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return Tables\RolesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function getNavigationItems(): array
    {
        return parent::getNavigationItems();
    }
}
