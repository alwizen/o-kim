<?php

namespace App\Filament\Resources\InspectionChecklists\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Butir Pemeriksaan';

    protected static ?string $modelLabel = 'Butir';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('item_code')
                ->label('Kode Butir')
                ->required()
                ->maxLength(30)
                ->helperText('Contoh: HSSE-01'),

            TextInput::make('label')
                ->label('Nama Butir')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            Textarea::make('description')
                ->label('Deskripsi / Panduan')
                ->rows(2)
                ->maxLength(1000)
                ->columnSpanFull(),

            TextInput::make('sort_order')
                ->label('Urutan')
                ->numeric()
                ->default(0)
                ->minValue(0),

            Toggle::make('is_required')
                ->label('Wajib diselesaikan')
                ->default(true),

            Toggle::make('is_active')
                ->label('Aktif')
                ->default(true),

            Repeater::make('points')
                ->relationship('points')
                ->label('Sub-butir Pemeriksaan')
                ->helperText('Poin rinci yang dicek petugas pada butir ini (mis. lokasi pemasangan, kondisi komponen).')
                ->addActionLabel('Tambah Sub-butir')
                ->reorderableWithButtons()
                ->orderColumn('sort_order')
                ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                ->collapsible()
                ->cloneable()
                ->schema([
                    TextInput::make('label')
                        ->label('Sub-butir')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true)
                        ->columnSpanFull(),
                ])
                ->columns(1)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                TextColumn::make('item_code')
                    ->label('Kode')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('label')
                    ->label('Nama Butir')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('points_count')
                    ->label('Sub-butir')
                    ->badge()
                    ->counts('points'),
                TextColumn::make('sort_order')
                    ->label('Urutan')
                    ->sortable(),
                IconColumn::make('is_required')
                    ->label('Wajib')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Butir')
                    ->modalWidth('3xl'),
            ])
            ->recordActions([
                EditAction::make()->modalWidth('3xl'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
