<?php

namespace App\Filament\Transportir\Resources\Findings\Tables;

use App\Enums\FindingStatus;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FindingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('application.application_number')
                    ->label('No. Pengajuan')
                    ->searchable(),
                TextColumn::make('department')
                    ->label('Fungsi')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('description')
                    ->label('Temuan')
                    ->limit(60)
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (FindingStatus $state) => $state->label())
                    ->color(fn (FindingStatus $state) => match ($state) {
                        FindingStatus::Open => 'danger',
                        FindingStatus::SubmittedForVerification => 'warning',
                        FindingStatus::Verified => 'success',
                        FindingStatus::Rejected => 'danger',
                    }),
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
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
