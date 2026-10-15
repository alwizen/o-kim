<?php

namespace App\Filament\Transportir\Resources\Findings\Pages;

use App\Enums\FindingStatus;
use App\Filament\Transportir\Resources\Findings\FindingResource;
use App\Services\Finding\FindingService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

class ViewFinding extends ViewRecord
{
    protected static string $resource = FindingResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Detail Temuan')
                ->columns(2)
                ->schema([
                    TextEntry::make('application.application_number')->label('No. Pengajuan'),
                    TextEntry::make('department')->label('Fungsi')->badge()->formatStateUsing(fn ($state) => $state->label()),
                    TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => $state->label()),
                    TextEntry::make('created_at')->label('Dibuat')->dateTime('d M Y H:i'),
                    TextEntry::make('description')->label('Deskripsi Temuan')->columnSpanFull(),
                ]),
            Section::make('Riwayat Tindak Lanjut')
                ->schema([
                    RepeatableEntry::make('actions')
                        ->label('')
                        ->schema([
                            TextEntry::make('description')->label('Tindak Lanjut'),
                            TextEntry::make('submitter.name')->label('Oleh'),
                            TextEntry::make('created_at')->label('Waktu')->dateTime('d M Y H:i'),
                            TextEntry::make('attachments_count')
                                ->label('Lampiran')
                                ->state(fn ($record) => $record->attachments()->count().' berkas'),
                        ])
                        ->columns(2),
                ]),
            Section::make('Riwayat Verifikasi')
                ->schema([
                    RepeatableEntry::make('verifications')
                        ->label('')
                        ->schema([
                            TextEntry::make('decision')
                                ->label('Keputusan')
                                ->badge()
                                ->formatStateUsing(fn ($state) => $state === 'accepted' ? 'Diterima' : 'Ditolak')
                                ->color(fn ($state) => $state === 'accepted' ? 'success' : 'danger'),
                            TextEntry::make('verifier.name')->label('Diverifikasi Oleh'),
                            TextEntry::make('verified_at')->label('Waktu')->dateTime('d M Y H:i'),
                            TextEntry::make('notes')->label('Catatan')->placeholder('—'),
                        ])
                        ->columns(2),
                ])
                ->visible(fn ($record) => $record->verifications()->exists()),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('submit_action')
                ->label('Kirim Tindak Lanjut')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('primary')
                ->visible(fn (): bool => in_array(
                    $this->record->status,
                    [FindingStatus::Open, FindingStatus::Rejected],
                    true
                ))
                ->schema([
                    Textarea::make('description')
                        ->label('Deskripsi Tindak Lanjut')
                        ->required()
                        ->maxLength(2000),
                    FileUpload::make('attachments')
                        ->label('Bukti (PDF/JPG/PNG, maks 5 MB per berkas)')
                        ->multiple()
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                        ->maxSize(5120)
                        ->disk('local')
                        ->storeFiles(false)
                        ->maxFiles(5),
                ])
                ->action(function (array $data): void {
                    try {
                        app(FindingService::class)->submitAction(
                            $this->record,
                            auth()->user(),
                            $data['description'],
                            $data['attachments'] ?? []
                        );
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title('Gagal mengirim tindak lanjut')
                            ->body(collect($e->errors())->flatten()->first())
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Tindak lanjut terkirim, menunggu verifikasi petugas')
                        ->success()
                        ->send();
                }),
        ];
    }
}
