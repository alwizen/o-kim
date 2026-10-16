<?php

namespace App\Filament\Resources\Transporters\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TransporterInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data Akun')
                ->columnSpanFull()
                ->columns(4)
                ->schema([
                    TextEntry::make('name')->label('Nama PIC'),
                    TextEntry::make('email'),
                    TextEntry::make('account_status')
                        ->label('Status Akun')
                        ->badge()
                        ->formatStateUsing(fn ($state) => $state->label()),
                    TextEntry::make('created_at')
                        ->label('Terdaftar Pada')
                        ->dateTime('d M Y H:i'),

                    TextEntry::make('transporters.company_name')
                        ->label('Nama Perusahaan')
                        ->placeholder('Belum mengisi profil'),
                    TextEntry::make('transporters.phone')
                        ->label('Telepon Perusahaan')
                        ->placeholder('—'),
                    TextEntry::make('transporters.company_address')
                        ->label('Alamat Perusahaan')
                        ->placeholder('—')
                        ->columnSpanFull(),
                    TextEntry::make('transporters.legal_identifier')
                        ->label('Nomor Identitas Perusahaan')
                        ->placeholder('—'),

                    TextEntry::make('reviewer.name')->label('Diputuskan Oleh')->placeholder('—'),
                    TextEntry::make('reviewed_at')->label('Waktu Keputusan')->dateTime('d M Y H:i')->placeholder('—'),
                    TextEntry::make('rejection_reason')->label('Alasan Penolakan')->placeholder('—')->columnSpanFull(),
                ]),
        ]);
    }
}
