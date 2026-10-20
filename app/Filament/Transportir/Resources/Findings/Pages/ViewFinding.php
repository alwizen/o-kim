<?php

namespace App\Filament\Transportir\Resources\Findings\Pages;

use App\Enums\FindingStatus;
use App\Filament\Transportir\Resources\Findings\FindingResource;
use App\Services\Finding\FindingService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Actions;
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
                ->columns(4)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('application.application_number')->label('No. Pengajuan'),
                    TextEntry::make('department')->label('Fungsi')->badge()->formatStateUsing(fn ($state) => $state->label()),
                    TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => $state->label()),
                    TextEntry::make('created_at')->label('Dibuat')->dateTime('d M Y H:i'),
                    TextEntry::make('description')->label('Deskripsi Temuan')->columnSpanFull(),
                ]),
            Section::make('Dokumentasi Temuan')
                ->description('Foto atau dokumen dari petugas pemeriksa terkait butir ini.')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('inspectionResult.attachments')
                        ->hiddenLabel()
                        ->contained(false)
                        ->placeholder('Tidak ada dokumentasi')
                        ->schema([
                            TextEntry::make('original_filename')
                                ->label('Berkas')
                                ->formatStateUsing(fn ($state, $record) => $state.' ('.number_format($record->file_size / 1024, 0).' KB)'),
                            Actions::make([
                                Action::make('preview_inspection_attachment')
                                    ->label('Pratinjau')
                                    ->icon('heroicon-o-eye')
                                    ->color('gray')
                                    ->url(fn ($record): string => route('inspection-attachments.preview', $record))
                                    ->openUrlInNewTab(),
                                Action::make('download_inspection_attachment')
                                    ->label('Unduh')
                                    ->icon('heroicon-o-arrow-down-tray')
                                    ->color('gray')
                                    ->url(fn ($record): string => route('inspection-attachments.download', $record)),
                            ]),
                        ])
                        ->columns(2),
                ]),
            Section::make('Riwayat Tindak Lanjut')
                // ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('actions')
                        ->columnSpanFull()
                        ->contained(false)
                        ->hiddenLabel()
                        ->table([
                            TableColumn::make('Tindak Lanjut'),
                            TableColumn::make('Oleh'),
                            TableColumn::make('Waktu'),
                            TableColumn::make('Berkas Bukti'),

                        ])
                        ->schema([
                            TextEntry::make('description')->label('Tindak Lanjut'),
                            TextEntry::make('submitter.name')->label('Oleh'),
                            TextEntry::make('created_at')->label('Waktu')->dateTime('d M Y H:i'),
                            RepeatableEntry::make('attachments')
                                ->hiddenLabel()
                                ->contained(false)
                                ->placeholder('Tidak ada berkas')
                                ->schema([
                                    TextEntry::make('original_filename')
                                        ->label('Berkas')
                                        ->formatStateUsing(fn ($state, $record) => $state.' ('.number_format($record->file_size / 1024, 0).' KB)'),
                                    Actions::make([
                                        Action::make('preview')
                                            ->label('Pratinjau')
                                            ->icon('heroicon-o-eye')
                                            ->color('gray')
                                            ->url(fn ($record): string => route('finding-attachments.preview', $record))
                                            ->openUrlInNewTab(),
                                        Action::make('download')
                                            ->label('Unduh')
                                            ->icon('heroicon-o-arrow-down-tray')
                                            ->color('gray')
                                            ->url(fn ($record): string => route('finding-attachments.download', $record)),
                                    ]),
                                ])
                                ->columns(2),
                        ])
                        ->columns(4),
                ])
                ->visible(fn ($record) => $record->actions()->exists()),
            Section::make('Riwayat Verifikasi')
                ->schema([
                    RepeatableEntry::make('verifications')
                        ->hiddenLabel()
                        ->contained(false)
                        ->table([
                            TableColumn::make('Keputusan'),
                            TableColumn::make('Diverifikasi Oleh'),
                            TableColumn::make('Waktu'),
                            TableColumn::make('Catatan'),
                        ])
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
