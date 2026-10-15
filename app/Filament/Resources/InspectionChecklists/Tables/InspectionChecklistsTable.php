<?php

namespace App\Filament\Resources\InspectionChecklists\Tables;

use App\Enums\Department;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InspectionChecklistsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Judul')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('department')
                    ->label('Fungsi')
                    ->badge()
                    ->formatStateUsing(fn (Department $state) => $state->label())
                    ->color(fn (Department $state) => match ($state) {
                        Department::Hsse => 'danger',
                        Department::Distribusi => 'info',
                        Department::Qq => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('items_count')
                    ->label('Jumlah Butir')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                IconColumn::make('is_required')
                    ->label('Wajib')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('department')
                    ->label('Fungsi')
                    ->options(collect(Department::inspectingDepartments())->mapWithKeys(
                        fn (Department $d) => [$d->value => $d->label()]
                    )),
            ])
            ->defaultSort('department')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
