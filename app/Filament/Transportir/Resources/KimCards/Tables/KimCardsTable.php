<?php

namespace App\Filament\Transportir\Resources\KimCards\Tables;

use App\Enums\KimCardStatus;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class KimCardsTable
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
                TextColumn::make('issued_at')
                    ->label('Terbit')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label('Berlaku Hingga')
                    ->date('d M Y')
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
                Action::make('download')
                    ->label('Unduh PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn ($record): string => route('kim.download', $record)),
                Action::make('download_image')
                    ->label('Unduh Gambar (Kartu ATM)')
                    ->icon('heroicon-o-photo')
                    ->color('gray')
                    ->visible(fn (): bool => (bool) config('kim.image_download_enabled'))
                    ->url(fn ($record): string => route('kim.download-image', $record)),
                ViewAction::make(),
            ]);
    }
}
