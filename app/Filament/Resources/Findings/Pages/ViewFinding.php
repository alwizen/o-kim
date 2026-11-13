<?php

namespace App\Filament\Resources\Findings\Pages;

use App\Enums\DispensationAction;
use App\Enums\FindingStatus;
use App\Filament\Resources\Findings\Actions\DispensationActions;
use App\Filament\Resources\Findings\FindingResource;
use App\Models\Finding;
use App\Models\FindingAction;
use App\Services\Audit\ActivityLogger;
use App\Services\Finding\FindingService;
use Filament\Actions\Action;
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
            'application.transporter',
            'actions.submitter',
            'actions.attachments',
            'dispensator',
            'dispensations.user',
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
                    TextEntry::make('application.transporter.company_name')->label('Perusahaan'),
                    TextEntry::make('department')->label('Fungsi')->badge()->formatStateUsing(fn ($state) => $state->label()),
                    TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => $state->label()),
                    TextEntry::make('description')->label('Deskripsi Temuan')->columnSpanFull(),
                ]),
            Section::make('Dispensasi FTM')
                ->columnSpanFull()
                ->columns(4)
                ->visible(fn (Finding $record): bool => $record->dispensation_reason !== null
                    || $record->dispensations->isNotEmpty())
                ->schema([
                    TextEntry::make('dispensation_due_date')
                        ->label('Batas Perbaikan')
                        ->date('d M Y')
                        ->placeholder('—')
                        ->badge()
                        ->color(fn (Finding $record): string => $record->isDispensationExpired() ? 'danger' : 'warning'),
                    TextEntry::make('dispensated_by')
                        ->label('Diberikan Oleh')
                        ->formatStateUsing(fn ($state, Finding $record): string => $record->dispensator?->name ?? '—'),
                    TextEntry::make('dispensated_at')
                        ->label('Waktu Dispensasi')
                        ->dateTime('d M Y H:i')
                        ->placeholder('—'),
                    TextEntry::make('dispensation_state')
                        ->label('Kondisi')
                        ->state(fn (Finding $record): string => match (true) {
                            $record->status !== FindingStatus::Dispensation => 'Tidak aktif',
                            $record->isDispensationExpired() => 'BATAS TERLAMPAUI',
                            default => 'Aktif',
                        })
                        ->badge()
                        ->color(fn (Finding $record): string => match (true) {
                            $record->status !== FindingStatus::Dispensation => 'gray',
                            $record->isDispensationExpired() => 'danger',
                            default => 'success',
                        }),
                    TextEntry::make('dispensation_reason')
                        ->label('Alasan Dispensasi')
                        ->placeholder('—')
                        ->columnSpanFull(),
                    RepeatableEntry::make('dispensations')
                        ->label('Riwayat Dispensasi')
                        ->columnSpanFull()
                        ->contained(false)
                        ->placeholder('Belum ada riwayat')
                        ->table([
                            TableColumn::make('Aksi'),
                            TableColumn::make('Oleh'),
                            TableColumn::make('Batas'),
                            TableColumn::make('Waktu'),
                            TableColumn::make('Catatan'),
                        ])
                        ->schema([
                            TextEntry::make('action')
                                ->badge()
                                ->formatStateUsing(fn (DispensationAction $state): string => $state->label())
                                ->color(fn (DispensationAction $state): string => $state->color()),
                            TextEntry::make('user.name')->label('Oleh'),
                            TextEntry::make('due_date')->label('Batas')->date('d M Y')->placeholder('—'),
                            TextEntry::make('created_at')->label('Waktu')->dateTime('d M Y H:i'),
                            TextEntry::make('notes')->label('Catatan')->placeholder('—'),
                        ]),
                ]),
            Section::make('Tindak Lanjut Transportir')
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
        ]);
    }

    protected function getHeaderActions(): array
    {
        $latestAction = $this->record->actions->sortByDesc('id')->first();

        return [
            Action::make('verify_accept')
                ->label('Terima Tindak Lanjut')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status === FindingStatus::SubmittedForVerification
                    && $latestAction !== null
                    && auth()->user()->canVerifyFinding($this->record))
                ->action(function () use ($latestAction): void {
                    $this->verify($latestAction, true);
                }),
            Action::make('verify_reject')
                ->label('Tolak Tindak Lanjut')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => $this->record->status === FindingStatus::SubmittedForVerification
                    && $latestAction !== null
                    && auth()->user()->canVerifyFinding($this->record))
                ->schema([
                    Textarea::make('notes')
                        ->label('Catatan Penolakan')
                        ->required()
                        ->maxLength(1000),
                ])
                ->action(function (array $data) use ($latestAction): void {
                    $this->verify($latestAction, false, $data['notes']);
                }),
            // Pemberian dispensasi baru kini melalui menu "Pengajuan KIM Sementara"
            // agar alur selalu dimulai dari permohonan transportir.
            DispensationActions::extend(),
            DispensationActions::updateReason(),
            DispensationActions::revoke(),
        ];
    }

    private function verify(?FindingAction $action, bool $accepted, ?string $notes = null): void
    {
        try {
            app(FindingService::class)->verifyAction(
                $this->record,
                $action,
                auth()->user(),
                $accepted,
                $notes
            );
        } catch (ValidationException $e) {
            $reason = app(ActivityLogger::class)->firstError($e);

            app(ActivityLogger::class)->failureFromValidation(
                'finding',
                $this->record->application,
                $e,
                ['finding_id' => $this->record->id, 'action' => 'verify_action'],
            );

            Notification::make()
                ->title('Verifikasi gagal')
                ->body($reason)
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title($accepted ? 'Tindak lanjut diterima, temuan ditutup' : 'Tindak lanjut ditolak')
            ->success()
            ->send();
    }
}
