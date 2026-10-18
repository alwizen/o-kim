<?php

namespace App\Filament\Resources\Findings\Pages;

use App\Enums\FindingStatus;
use App\Filament\Resources\Findings\FindingResource;
use App\Models\FindingAction;
use App\Services\Finding\FindingService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
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
                ->columns(4)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('application.application_number')->label('No. Pengajuan'),
                    TextEntry::make('application.transporter.company_name')->label('Perusahaan'),
                    TextEntry::make('department')->label('Fungsi')->badge()->formatStateUsing(fn ($state) => $state->label()),
                    TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => $state->label()),
                    TextEntry::make('description')->label('Deskripsi Temuan')->columnSpanFull(),
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
                                ])
                                ->columns(3),
                        ])
                        ->columns(4),
                ])
                ->visible(fn ($record) => $record->actions()->exists()),
        ]);
    }

    protected function getHeaderActions(): array
    {
        $latestAction = $this->record->actions()->latest('id')->first();

        return [
            Action::make('verify_accept')
                ->label('Terima Tindak Lanjut')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status === FindingStatus::SubmittedForVerification
                    && $latestAction !== null
                    && auth()->user()->can('inspect_'.$this->record->department->value))
                ->action(function () use ($latestAction): void {
                    $this->verify($latestAction, true);
                }),
            Action::make('verify_reject')
                ->label('Tolak Tindak Lanjut')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => $this->record->status === FindingStatus::SubmittedForVerification
                    && $latestAction !== null
                    && auth()->user()->can('inspect_'.$this->record->department->value))
                ->schema([
                    Textarea::make('notes')
                        ->label('Catatan Penolakan')
                        ->required()
                        ->maxLength(1000),
                ])
                ->action(function (array $data) use ($latestAction): void {
                    $this->verify($latestAction, false, $data['notes']);
                }),
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
            Notification::make()
                ->title('Verifikasi gagal')
                ->body(collect($e->errors())->flatten()->first())
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
