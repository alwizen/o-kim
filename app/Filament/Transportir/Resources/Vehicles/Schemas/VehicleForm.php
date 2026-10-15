<?php

namespace App\Filament\Transportir\Resources\Vehicles\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class VehicleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('registration_number')
                ->label('Nomor Polisi (NOPOL)')
                ->required()
                ->maxLength(20)
                ->unique(ignoreRecord: true),
            TextInput::make('brand')
                ->label('Merek')
                ->maxLength(100),
            TextInput::make('model')
                ->label('Model')
                ->maxLength(100),
            TextInput::make('manufacture_year')
                ->label('Tahun Pembuatan')
                ->numeric()
                ->minValue(1990)
                ->maxValue((int) date('Y')),
            TextInput::make('tank_capacity')
                ->label('Kapasitas Tangki (Liter)')
                ->numeric()
                ->minValue(1),
        ]);
    }
}
