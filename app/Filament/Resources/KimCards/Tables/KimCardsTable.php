<?php

namespace App\Filament\Resources\KimCards\Tables;

use App\Enums\KimCardStatus;
use App\Services\Kim\KimValidityService;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class KimCardsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->paginated([50, 100, 'all'])
            ->columns([
                TextColumn::make('no')
                    ->label('No')
                    ->rowIndex()
                    ->alignCenter()
                    ->width(1)
                    ->grow(false),
                TextColumn::make('kim_number')
                    ->label('Nomor KIM')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Nomor KIM disalin'),
                TextColumn::make('application.vehicle.registration_number')
                    ->label('NOPOL')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('application.transporter.company_name')
                    ->label('Perusahaan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('issued_at')
                    ->label('Terbit')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label('Berlaku Hingga')
                    ->formatStateUsing(fn ($state, $record): string => $state->format('d M Y')
                        .app(KimValidityService::class)->validityLabel($record->application))
                    ->sortable(),
                TextColumn::make('is_dispensation')
                    ->label('Jenis KIM')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state ? 'Sementara' : 'Normal')
                    ->color(fn ($state): string => $state ? 'warning' : 'gray')
                    ->toggleable(),
                TextColumn::make('dispensation_expires_at')
                    ->label('Batas Dispensasi')
                    ->date('d M Y')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (KimCardStatus $state, $record): string => $record->currentStatus()->label())
                    ->color(fn (KimCardStatus $state, $record): string => match ($record->currentStatus()) {
                        KimCardStatus::Active => 'success',
                        KimCardStatus::Expired => 'warning',
                        KimCardStatus::Revoked => 'danger',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status Tersimpan')
                    ->options(collect(KimCardStatus::cases())->mapWithKeys(
                        fn (KimCardStatus $s) => [$s->value => $s->label()]
                    )),
            ])
            ->defaultSort('issued_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
