<?php

namespace App\Filament\Transportir\Resources\Vehicles\Schemas;

use Filament\Forms\Components\Select;
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
                        ->placeholder('Contoh: G1234TGL')
                        ->maxLength(20)
                        ->unique(ignoreRecord: true)
                        ->helperText('Contoh: G 1234 TGL')
                        ->columnSpanFull(),
                    TextInput::make('brand')
                        ->label('Nama Headtruck')
                        ->maxLength(100)
                        ->placeholder('Hino / Mitsubishi / Isuzu'),
                    TextInput::make('manufacture_year')
                        ->label('Tahun Pembuatan')
                        ->numeric()
                        ->placeholder('Contoh: 2020')
                        ->minValue(1990)
                        ->maxValue((int) date('Y')),
                    TextInput::make('karoseri_builder')
                        ->label('Nama Pembuat Karoseri')
                        ->maxLength(100)
                        ->placeholder('Contoh: PT Karoseri Maju'),
                    TextInput::make('karoseri_built_period')
                        ->label('Bulan & Tahun Pembuatan Karoseri')
                        ->placeholder('Contoh: 03/2021')
                        ->maxLength(20)
                        ->regex('/^\d{2}\/\d{4}$/')
                        ->helperText('Format: MM/YYYY'),
                    Select::make('model')
                        ->label('Model')
                        ->options([
                            'rgid' => 'Rigid',
                            'semi_trailer' => 'Semi-trailer',
                        ])
                        ->placeholder('Pilih Model'),
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
                    Select::make('tank_capacity')
                        ->label('Kapasitas Tangki (KL)')
                        ->options([
                            '5000' => '5 KL',
                            '8000' => '8 KL',
                            '10000' => '10 KL',
                            '15000' => '15 KL',
                            '16000' => '16 KL',
                            '24000' => '24 KL',
                            '32000' => '32 KL',
                            '40000' => '40 KL',
                        ])
                        ->required(),
                    Select::make('transport_type')
                        ->label('Jenis Angkutan')
                        ->options([
                            'SPBU/Pertashop' => 'SPBU/Pertashop',
                            'industri_agen' => 'Industri Agen',
                            'industri_non_agen' => 'Industri Non-Agen',
                            'konsinyasi' => 'Konsinyasi',
                            'chemical' => 'Chemical',
                        ])
                        ->required(),
                    Select::make('tank_material')
                        ->label('Bahan Tangki')
                        ->options([
                            'alumunium' => 'Alumunium',
                            'stainless_steel' => 'Stainless Steel',
                            'mild_steel' => 'Mild Steel',
                        ]),
                    TextInput::make('tank_wall_thickness_mm')
                        ->label('Ketebalan Tangki (mm)')
                        ->numeric()
                        ->minValue(0.1)
                        ->maxValue(99.99)
                        ->placeholder('Contoh: 5.5')
                        ->suffix('mm'),
                ]),
        ]);
    }
}
