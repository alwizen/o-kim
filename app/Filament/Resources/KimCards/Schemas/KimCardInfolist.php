<?php

namespace App\Filament\Resources\KimCards\Schemas;

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
                    TextEntry::make('status')
                        ->label('Status Tersimpan')
                        ->badge()
                        ->formatStateUsing(fn (KimCardStatus $state): string => $state->label())
                        ->color(fn (KimCardStatus $state): string => match ($state) {
                            KimCardStatus::Active => 'success',
                            KimCardStatus::Expired => 'warning',
                            KimCardStatus::Revoked => 'danger',
                        }),
                    TextEntry::make('updated_at')
                        ->label('Terakhir Diperbarui')
                        ->dateTime('d M Y H:i'),
                ]),
            Section::make('Kendaraan & Pengajuan')
                ->columns(2)
                ->schema([
                    TextEntry::make('application.vehicle.registration_number')
                        ->label('NOPOL')
                        ->placeholder('—'),
                    TextEntry::make('application.transporter.company_name')
                        ->label('Perusahaan')
                        ->placeholder('—'),
                    TextEntry::make('application.application_number')
                        ->label('No. Pengajuan')
                        ->placeholder('—'),
                    TextEntry::make('application.application_type')
                        ->label('Jenis Pengajuan')
                        ->formatStateUsing(fn ($state) => $state->label())
                        ->placeholder('—'),
                    TextEntry::make('application.vehicle.chassis_number')
                        ->label('No. Rangka')
                        ->placeholder('—'),
                    TextEntry::make('application.vehicle.engine_number')
                        ->label('No. Mesin')
                        ->placeholder('—'),
                ]),
            Section::make('Validasi Publik')
                ->schema([
                    TextEntry::make('qr_validation_url')
                        ->label('Tautan Validasi QR')
                        ->state(fn ($record): string => route('kim.validate', $record->qr_token))
                        ->copyable()
                        ->copyMessage('Tautan disalin')
                        ->url(fn ($record): string => route('kim.validate', $record->qr_token), shouldOpenInNewTab: true),
                ]),
        ]);
    }
}
