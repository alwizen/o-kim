<?php

namespace App\Filament\Resources\InspectionChecklists\Schemas;

use App\Enums\Department;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InspectionChecklistForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi Checklist')
                ->description('Checklist dikelompokkan per fungsi pemeriksa.')
                ->columns(2)
                ->schema([
                    Select::make('department')
                        ->label('Fungsi')
                        ->options(collect(Department::inspectingDepartments())->mapWithKeys(
                            fn (Department $d) => [$d->value => $d->label()]
                        ))
                        ->required()
                        ->native(false),
                    TextInput::make('code')
                        ->label('Kode')
                        ->required()
                        ->maxLength(30)
                        ->unique(ignoreRecord: true)
                        ->helperText('Contoh: HSSE-STD'),
                    TextInput::make('title')
                        ->label('Judul Checklist')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Textarea::make('description')
                        ->label('Deskripsi')
                        ->rows(2)
                        ->maxLength(1000)
                        ->columnSpanFull(),
                ]),

            Section::make('Status')
                ->columns(2)
                ->schema([
                    Toggle::make('is_required')
                        ->label('Wajib diselesaikan')
                        ->default(true),
                    Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true),
                ]),
        ]);
    }
}
