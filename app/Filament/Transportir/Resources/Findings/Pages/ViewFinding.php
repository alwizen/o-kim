<?php

namespace App\Filament\Transportir\Resources\Findings\Pages;

use App\Enums\FindingStatus;
use App\Filament\Transportir\Resources\Findings\FindingResource;
use App\Services\Audit\ActivityLogger;
use App\Services\Finding\DispensationService;
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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ViewFinding extends ViewRecord
{
    protected static string $resource = FindingResource::class;

    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->loadMissing([
            'application',
            'inspectionResult.attachments',
            'actions.submitter',
            'actions.attachments',
            'verifications.verifier',
            'dispensator',
            'dispensationRequests.requester',
            'dispensationRequests.decider',
        ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Detail Temuan')
                ->columns(4)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('application.application_number')->label('No. Pengajuan'),
                    TextEntry::make('department')->label('Fungsi')->badge()->formatStateUsing(fn ($state) => $state->label()),
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn ($state) => $state->label())
                        ->color(fn ($state) => $state->color()),
                    TextEntry::make('created_at')->label('Dibuat')->dateTime('d M Y H:i'),
                    TextEntry::make('description')->label('Deskripsi Temuan')->columnSpanFull(),
                ]),
            Section::make('Dispensasi FTM')
                ->description('Temuan ini didispensasi oleh Fuel Terminal Manager. Selesaikan perbaikan sebelum batas waktu.')
                ->columns(3)
                ->columnSpanFull()
                ->visible(fn ($record): bool => $record->dispensation_reason !== null)
                ->schema([
                    TextEntry::make('dispensation_due_date')
                        ->label('Batas Perbaikan')
                        ->date('d M Y')
                        ->badge()
                        ->color(fn ($record): string => $record->isDispensationExpired() ? 'danger' : 'warning'),
                    TextEntry::make('dispensator.name')->label('Diberikan Oleh')->placeholder('—'),
                    TextEntry::make('dispensation_state')
                        ->label('Kondisi')
                        ->state(fn ($record): string => $record->isDispensationExpired() ? 'BATAS TERLAMPAUI' : 'Aktif')
                        ->badge()
                        ->color(fn ($record): string => $record->isDispensationExpired() ? 'danger' : 'success'),
                    TextEntry::make('dispensation_reason')
                        ->label('Alasan Dispensasi')
                        ->columnSpanFull(),
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
                ->columnSpanFull()
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
                ->visible(fn ($record) => $record->actions->isNotEmpty()),
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
                ->visible(fn ($record) => $record->verifications->isNotEmpty()),
            Section::make('Permohonan KIM Sementara (Dispensasi)')
                ->description('Ajukan permohonan penundaan perbaikan bila temuan tidak dapat diselesaikan segera (mis. menunggu suku cadang).')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('dispensationRequests')
                        ->hiddenLabel()
                        ->contained(false)
                        ->table([
                            TableColumn::make('Status'),
                            TableColumn::make('Alasan'),
                            TableColumn::make('Diajukan Oleh'),
                            TableColumn::make('Waktu'),
                            TableColumn::make('Batas Perbaikan'),
                            TableColumn::make('Catatan FTM'),
                        ])
                        ->schema([
                            TextEntry::make('status')
                                ->label('Status')
                                ->badge()
                                ->formatStateUsing(fn ($state) => $state->label())
                                ->color(fn ($state) => $state->color()),
                            TextEntry::make('reason')->label('Alasan'),
                            TextEntry::make('requester.name')->label('Diajukan Oleh')->placeholder('—'),
                            TextEntry::make('created_at')->label('Waktu')->dateTime('d M Y H:i'),
                            TextEntry::make('due_date')->label('Batas Perbaikan')->date('d M Y')->placeholder('—'),
                            TextEntry::make('decision_notes')->label('Catatan FTM')->placeholder('—'),
                        ])
                        ->columns(2),
                ])
                ->visible(fn ($record) => $record->dispensationRequests->isNotEmpty()),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('request_dispensation')
                ->label('Ajukan Dispensasi')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->visible(fn (): bool => in_array($this->record->status, FindingStatus::actionableCases(), true)
                    && $this->record->pendingDispensationRequest() === null)
                ->modalHeading('Ajukan Permohonan KIM Sementara')
                ->modalDescription('Permohonan akan ditinjau oleh Fuel Terminal Manager. Temuan tetap dianggap terbuka sampai permohonan disetujui.')
                ->schema([
                    Textarea::make('reason')
                        ->label('Alasan Permohonan')
                        ->helperText('Contoh: perbaikan menunggu suku cadang (inden) yang diperkirakan tiba dalam 2 minggu.')
                        ->required()
                        ->minLength(10)
                        ->maxLength(1000)
                        ->rows(4),
                ])
                ->action(function (array $data): void {
                    try {
                        app(DispensationService::class)->request(
                            $this->record,
                            auth()->user(),
                            $data['reason'],
                        );
                    } catch (ValidationException $e) {
                        $reason = app(ActivityLogger::class)->firstError($e);

                        app(ActivityLogger::class)->failureFromValidation(
                            'dispensation',
                            $this->record->application,
                            $e,
                            ['finding_id' => $this->record->id, 'action' => 'request'],
                        );

                        Notification::make()
                            ->title('Gagal mengajukan dispensasi')
                            ->body($reason)
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Permohonan terkirim')
                        ->body('Menunggu keputusan Fuel Terminal Manager.')
                        ->success()
                        ->send();
                }),
            Action::make('submit_action')
                ->label('Kirim Tindak Lanjut')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('primary')
                ->visible(fn (): bool => in_array(
                    $this->record->status,
                    FindingStatus::actionableCases(),
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
                        ->rules(['mimes:pdf,jpg,jpeg,png'])
                        ->maxSize(5120)
                        ->disk(config('media.disk'))
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
                        $reason = app(ActivityLogger::class)->firstError($e);

                        app(ActivityLogger::class)->failureFromValidation(
                            'finding',
                            $this->record->application,
                            $e,
                            ['finding_id' => $this->record->id, 'action' => 'submit_action'],
                        );

                        Notification::make()
                            ->title('Gagal mengirim tindak lanjut')
                            ->body($reason)
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
