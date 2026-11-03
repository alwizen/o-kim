<?php

namespace App\Filament\Transportir\Resources\Findings\Tables;

use App\Enums\FindingStatus;
use App\Models\Finding;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class FindingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('no')
                    ->label('No')
                    ->rowIndex()
                    ->alignCenter()
                    ->width(1)
                    ->grow(false),
                TextColumn::make('application.application_number')
                    ->label('No. Pengajuan')
                    ->searchable(),
                TextColumn::make('department')
                    ->label('Fungsi')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('inspectionResult.item.label')
                    ->label('Jenis Temuan (Checklist)')
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state, $record) => $record->inspectionResult?->item
                        ? $record->inspectionResult->item->item_code.' — '.$record->inspectionResult->item->label
                        : null)
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('description')
                    ->label('Temuan')
                    ->limit(60)
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (FindingStatus $state) => $state->label())
                    ->color(fn (FindingStatus $state) => $state->color()),
                TextColumn::make('dispensation_due_date')
                    ->label('Batas Dispensasi')
                    ->date('d M Y')
                    ->placeholder('—')
                    ->badge()
                    ->color(fn ($state, Finding $record): string => $record->isDispensationExpired() ? 'danger' : 'warning')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(FindingStatus::cases())->mapWithKeys(
                        fn (FindingStatus $s) => [$s->value => $s->label()]
                    )),
                Filter::make('created_at')
                    ->label('Tanggal Temuan')
                    ->schema([
                        DatePicker::make('created_from')
                            ->label('Dari tanggal'),
                        DatePicker::make('created_until')
                            ->label('Sampai tanggal'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['created_from'] ?? null,
                            fn (Builder $query, string $date): Builder => $query
                                ->whereDate('created_at', '>=', $date),
                        )
                        ->when(
                            $data['created_until'] ?? null,
                            fn (Builder $query, string $date): Builder => $query
                                ->whereDate('created_at', '<=', $date),
                        ))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['created_from'] ?? null) {
                            $indicators[] = 'Temuan dari '.Carbon::parse($data['created_from'])->translatedFormat('d M Y');
                        }

                        if ($data['created_until'] ?? null) {
                            $indicators[] = 'Temuan sampai '.Carbon::parse($data['created_until'])->translatedFormat('d M Y');
                        }

                        return $indicators;
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
