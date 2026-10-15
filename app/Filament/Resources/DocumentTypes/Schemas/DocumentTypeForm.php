<?php

namespace App\Filament\Resources\DocumentTypes\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DocumentTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->label('Kode')
                ->required()
                ->maxLength(30)
                ->unique(ignoreRecord: true),
            TextInput::make('name')
                ->label('Nama Dokumen')
                ->required()
                ->maxLength(255),
            Textarea::make('description')
                ->label('Deskripsi')
                ->rows(2)
                ->maxLength(1000),
            Toggle::make('is_required')
                ->label('Wajib untuk pengajuan KIM'),
            Toggle::make('has_expiry_date')
                ->label('Memiliki tanggal kedaluwarsa')
                ->default(true),
            Toggle::make('is_active')
                ->label('Aktif')
                ->default(true),
        ]);
    }
}
