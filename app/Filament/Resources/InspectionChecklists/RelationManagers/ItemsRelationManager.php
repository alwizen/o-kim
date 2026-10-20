<?php

namespace App\Filament\Resources\InspectionChecklists\RelationManagers;

use App\Models\InspectionChecklist;
use App\Models\InspectionItem;
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
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Butir Pemeriksaan';

    protected static ?string $modelLabel = 'Butir';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Butir Pemeriksaan')
                // ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('item_code')
                        ->label('Kode Butir')
                        ->maxLength(30)
                        ->required(fn (string $operation) => $operation === 'edit')
                        ->unique(
                            table: InspectionItem::class,
                            column: 'item_code',
                            modifyRuleUsing: fn (Unique $rule, ?string $operation, ?InspectionItem $record, RelationManager $livewire) => $rule
                                ->where('inspection_checklist_id', $livewire->ownerRecord->id)
                                ->when($operation === 'edit', fn (Unique $rule) => $rule->ignore($record?->id)),
                        )
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Otomatis dibuat saat menambah butir baru (contoh: HSSE-01).')
                        ->disabled(fn (string $operation) => $operation === 'create')
                        ->dehydrated()
                        ->helperText(fn (string $operation) => $operation === 'create'
                            ? 'Otomatis diisi oleh sistem.'
                            : 'Tidak disarankan diubah karena sudah dipakai pada hasil pemeriksaan.'),

                    TextInput::make('sort_order')
                        ->label('Urutan')
                        ->numeric()
                        ->default(0)
                        ->minValue(0),

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
                ]),

            Section::make('Status')
                // ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Toggle::make('is_required')
                        ->label('Wajib diselesaikan')
                        ->default(true),
                    Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true),
                ]),

            Section::make('Sub-butir Pemeriksaan')
                ->description('Poin rinci yang dicek petugas pada butir ini (mis. lokasi pemasangan, kondisi komponen).')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('points')
                        ->relationship('points')
                        ->hiddenLabel()
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
                        ->columns(1),
                ]),
        ]);
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
                    ->modalWidth('3xl')
                    ->using(function (array $data): InspectionItem {
                        $data['item_code'] = $data['item_code']
                            ?? $this->generateItemCode($this->getOwnerRecord());

                        return $this->getRelationship()->create($data);
                    }),
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

    /**
     * Buat kode butir berikutnya untuk checklist ini, format {PREFIX}-{NN}.
     * Contoh: HSSE-01, HSSE-02, dst. Nomor dilanjutkan dari kode terbesar.
     */
    private function generateItemCode(InspectionChecklist $checklist): string
    {
        // Prefix dari kode checklist (mis. HSSE-STD) dipangkas ke segmen departemen: HSSE.
        $prefix = strtoupper(explode('-', $checklist->code)[0]);

        $lastNumber = InspectionItem::where('inspection_checklist_id', $checklist->id)
            ->where('item_code', 'like', $prefix.'-%')
            ->get()
            ->map(fn (InspectionItem $item) => (int) substr($item->item_code, strlen($prefix) + 1))
            ->max() ?? 0;

        $maxAttempts = 100;
        $number = $lastNumber;

        do {
            $number++;
            $code = $prefix.'-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            $attempts = ($attempts ?? 0) + 1;
        } while (
            InspectionItem::where('inspection_checklist_id', $checklist->id)
                ->where('item_code', $code)
                ->exists()
            && $attempts < $maxAttempts
        );

        return $code;
    }
}
