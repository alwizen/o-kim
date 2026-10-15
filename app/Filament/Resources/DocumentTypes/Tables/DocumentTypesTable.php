<?php

namespace App\Filament\Resources\DocumentTypes\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DocumentTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable()->sortable(),
                TextColumn::make('name')->label('Nama Dokumen')->searchable(),
                IconColumn::make('is_required')->label('Wajib')->boolean(),
                IconColumn::make('has_expiry_date')->label('Ada Kedaluwarsa')->boolean(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
