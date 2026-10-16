<?php

namespace App\Filament\Resources\Vehicles\Pages;

use App\Enums\ApplicationStatus;
use App\Enums\DocumentVerificationStatus;
use App\Enums\KimCardStatus;
use App\Filament\Resources\Vehicles\VehicleResource;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewVehicle extends ViewRecord
{
    protected static string $resource = VehicleResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data Kendaraan')
                ->columns(3)
                ->schema([
                    TextEntry::make('registration_number')->label('NOPOL'),
                    TextEntry::make('transporter.company_name')->label('Perusahaan'),
                    TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => $state->label()),
                    TextEntry::make('brand')->label('Merek')->placeholder('—'),
                    TextEntry::make('model')->label('Model')->placeholder('—'),
                    TextEntry::make('manufacture_year')->label('Tahun')->placeholder('—'),
                    TextEntry::make('tank_capacity')->label('Kapasitas Tangki (L)')->placeholder('—'),
                    TextEntry::make('chassis_number')->label('Nomor Rangka')->placeholder('—'),
                    TextEntry::make('engine_number')->label('Nomor Mesin')->placeholder('—'),
                    TextEntry::make('transport_type')->label('Jenis Angkutan')->placeholder('—'),
                    TextEntry::make('tank_material')->label('Bahan Tangki')->placeholder('—'),
                ]),
            Section::make('Dokumen Kendaraan')
                ->schema([
                    RepeatableEntry::make('documents')
                        ->label('')
                        ->schema([
                            TextEntry::make('documentType.name')->label('Jenis'),
                            TextEntry::make('document_number')->label('Nomor')->placeholder('—'),
                            TextEntry::make('expires_at')->label('Kedaluwarsa')->date('d M Y')->placeholder('—'),
                            TextEntry::make('verification_status')
                                ->label('Verifikasi')
                                ->badge()
                                ->formatStateUsing(fn (DocumentVerificationStatus $state) => $state->label())
                                ->color(fn (DocumentVerificationStatus $state) => match ($state) {
                                    DocumentVerificationStatus::Pending => 'warning',
                                    DocumentVerificationStatus::Verified => 'success',
                                    DocumentVerificationStatus::Rejected => 'danger',
                                }),
                            TextEntry::make('is_current')
                                ->label('Status Versi')
                                ->state(fn ($record) => $record->isCurrentVersion() ? 'Berlaku' : 'Digantikan'),
                            Action::make('preview')
                                ->label('Pratinjau')
                                ->icon('heroicon-o-eye')
                                ->color('primary')
                                ->url(fn ($record): string => route('documents.preview', ['document' => $record->id]))
                                ->openUrlInNewTab(),
                        ])
                        ->columns(6),
                ]),
            Section::make('Riwayat Pengajuan KIM')
                ->schema([
                    RepeatableEntry::make('kimApplications')
                        ->label('')
                        ->schema([
                            TextEntry::make('application_number')->label('No. Pengajuan'),
                            TextEntry::make('application_type')->label('Jenis')->formatStateUsing(fn ($state) => $state->label()),
                            TextEntry::make('status')->badge()->formatStateUsing(fn (ApplicationStatus $state) => $state->label()),
                            TextEntry::make('created_at')->label('Dibuat')->dateTime('d M Y'),
                            TextEntry::make('kimCard.kim_number')->label('No. KIM')->placeholder('—'),
                            TextEntry::make('kimCard.status')
                                ->label('Status KIM')
                                ->badge()
                                ->placeholder('—')
                                ->formatStateUsing(fn ($state) => $state instanceof KimCardStatus ? $state->label() : $state)
                                ->color(fn ($state) => $state instanceof KimCardStatus && $state === KimCardStatus::Active ? 'success' : 'gray'),
                        ])
                        ->columns(3),
                ])
                ->visible(fn ($record) => $record->kimApplications()->exists()),
        ]);
    }
}
