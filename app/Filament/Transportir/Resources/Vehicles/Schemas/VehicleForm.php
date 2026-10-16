<?php

namespace App\Filament\Transportir\Resources\Vehicles\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VehicleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas Kendaraan')
                ->description('Nomor polisi dan spesifikasi dasar kendaraan tangki.')
                ->columns(2)
                ->schema([
                    TextInput::make('registration_number')
                        ->label('Nomor Polisi (NOPOL)')
                        ->required()
                        ->maxLength(20)
                        ->unique(ignoreRecord: true)
                        ->helperText('Contoh: G 1234 TGL')
                        ->columnSpanFull(),
                    TextInput::make('brand')
                        ->label('Merek')
                        ->maxLength(100)
                        ->placeholder('Hino / Mitsubishi / Isuzu'),
                    TextInput::make('model')
                        ->label('Model')
                        ->maxLength(100),
                    TextInput::make('chassis_number')
                        ->label('Nomor Rangka')
                        ->maxLength(100),
                    TextInput::make('engine_number')
                        ->label('Nomor Mesin')
                        ->maxLength(100),
                ]),

            Section::make('Spesifikasi Teknis')
                ->columns(2)
                ->schema([
                    TextInput::make('manufacture_year')
                        ->label('Tahun Pembuatan')
                        ->numeric()
                        ->minValue(1990)
                        ->maxValue((int) date('Y')),
                    TextInput::make('tank_capacity')
                        ->label('Kapasitas Tangki (Liter)')
                        ->numeric()
                        ->minValue(1),
                    TextInput::make('transport_type')
                        ->label('Jenis Angkutan')
                        ->maxLength(100)
                        ->placeholder('Contoh: BBM, Chemical, Gas'),
                    TextInput::make('tank_material')
                        ->label('Bahan Tangki')
                        ->maxLength(100)
                        ->placeholder('Contoh: Baja Karbon, Stainless Steel'),
                ]),
        ]);
    }
}
