<?php

namespace App\Filament\Resources\Roles\Tables;

use App\Enums\InternalRole;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('no')
                    ->label('No')
                    ->rowIndex()
                    ->alignCenter()
                    ->width(1)
                    ->grow(false),
                TextColumn::make('name')
                    ->label('Role')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => InternalRole::tryFrom($state)?->label() ?? Str::of($state)->replace('_', ' ')->title()->toString()),
                TextColumn::make('permissions_count')
                    ->counts('permissions')
                    ->label('Jumlah Permission')
                    ->sortable(),
                TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Jumlah Pengguna')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
