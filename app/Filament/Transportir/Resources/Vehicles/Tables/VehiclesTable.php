<?php

namespace App\Filament\Transportir\Resources\Vehicles\Tables;

use App\Enums\VehicleStatus;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VehiclesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('registration_number')
                    ->label('NOPOL')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('brand')
                    ->label('Merek')
                    ->placeholder('—'),
                TextColumn::make('model')
                    ->placeholder('—'),
                TextColumn::make('manufacture_year')
                    ->label('Tahun')
                    ->placeholder('—'),
                TextColumn::make('tank_capacity')
                    ->label('Kapasitas (L)')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (VehicleStatus $state) => $state->label())
                    ->color(fn (VehicleStatus $state) => $state === VehicleStatus::Active ? 'success' : 'gray'),
                TextColumn::make('documents_count')
                    ->label('Dokumen')
                    ->counts('documents'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
