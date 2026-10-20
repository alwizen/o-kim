<?php

namespace App\Filament\Transportir\Resources\KimCards\Schemas;

use App\Enums\KimCardStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class KimCardInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi KIM')
                ->columns(2)
                ->schema([
                    TextEntry::make('kim_number')
                        ->label('Nomor KIM')
                        ->copyable()
                        ->copyMessage('Nomor KIM disalin'),
                    TextEntry::make('status')
                        ->label('Status Saat Ini')
                        ->badge()
                        ->formatStateUsing(fn ($state, $record): string => $record->currentStatus()->label())
                        ->color(fn ($state, $record): string => match ($record->currentStatus()) {
                            KimCardStatus::Active => 'success',
                            KimCardStatus::Expired => 'warning',
                            KimCardStatus::Revoked => 'danger',
                        }),
                    TextEntry::make('issued_at')
                        ->label('Tanggal Terbit')
                        ->dateTime('d M Y H:i'),
                    TextEntry::make('expires_at')
                        ->label('Berlaku Hingga')
                        ->date('d M Y'),
                ]),
            Section::make('Kendaraan')
                ->columns(2)
                ->schema([
                    TextEntry::make('application.vehicle.registration_number')
                        ->label('NOPOL')
                        ->placeholder('—'),
                    TextEntry::make('application.vehicle.brand')
                        ->label('Nama Pabrikan/Karoseri')
                        ->placeholder('—'),
                ]),
        ]);
    }
}
