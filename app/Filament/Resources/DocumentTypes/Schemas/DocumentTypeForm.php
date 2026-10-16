<?php

namespace App\Filament\Resources\DocumentTypes\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DocumentTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi Jenis Dokumen')
                ->columns(2)
                ->schema([
                    TextInput::make('code')
                        ->label('Kode')
                        ->required()
                        ->maxLength(30)
                        ->unique(ignoreRecord: true)
                        ->helperText('Contoh: STNK'),
                    TextInput::make('name')
                        ->label('Nama Dokumen')
                        ->required()
                        ->maxLength(255),
                    Textarea::make('description')
                        ->label('Deskripsi')
                        ->rows(2)
                        ->maxLength(1000)
                        ->columnSpanFull(),
                ]),

            Section::make('Ketentuan')
                ->columns(3)
                ->schema([
                    Toggle::make('is_required')
                        ->label('Wajib untuk pengajuan KIM'),
                    Toggle::make('has_expiry_date')
                        ->label('Memiliki tanggal kedaluwarsa')
                        ->default(true),
                    Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true),
                ]),
        ]);
    }
}
