<?php

namespace App\Filament\Transportir\Resources\KimApplications\Pages;

use App\Enums\ApplicationStatus;
use App\Enums\FindingStatus;
use App\Filament\Transportir\Resources\KimApplications\KimApplicationResource;
use App\Services\Application\KimApplicationService;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ViewKimApplication extends ViewRecord
{
    protected static string $resource = KimApplicationResource::class;

    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->loadMissing([
            'findings.inspectionResult.item',
        ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi Pengajuan')
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    TextEntry::make('application_number')->label('No. Pengajuan'),
                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn ($state) => $state->label()),
                    TextEntry::make('application_type')
                        ->label('Jenis')
                        ->formatStateUsing(fn ($state) => $state->label()),
                    TextEntry::make('submitted_at')->label('Dikirim Pada')->dateTime('d M Y H:i')->placeholder('—'),
                    TextEntry::make('vehicle.registration_number')->label('NOPOL')->badge(),
                    TextEntry::make('vehicle.brand')->label('Merek')->placeholder('—'),
                    TextEntry::make('rejection_reason')->label('Alasan Penolakan')->placeholder('—')->columnSpanFull(),
                ]),
            Section::make('Temuan')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('findings')
                        ->hiddenLabel()
                        ->contained(false)
                        ->placeholder('Tidak ada temuan')
                        ->table([
                            TableColumn::make('Fungsi'),
                            TableColumn::make('Jenis Temuan (Checklist)'),
                            TableColumn::make('Deskripsi'),
                            TableColumn::make('Status'),
                            TableColumn::make('Waktu'),
                        ])
                        ->schema([
                            TextEntry::make('department')
                                ->label('Fungsi')
                                ->badge()
                                ->formatStateUsing(fn ($state) => $state->label()),
                            TextEntry::make('inspectionResult.item.label')
                                ->label('Jenis Temuan')
                                ->placeholder('—')
                                ->formatStateUsing(fn ($state, $record) => $record->inspectionResult?->item
                                    ? $record->inspectionResult->item->item_code.' — '.$record->inspectionResult->item->label
                                    : null),
                            TextEntry::make('description')->label('Deskripsi'),
                            TextEntry::make('status')
                                ->label('Status')
                                ->badge()
                                ->formatStateUsing(fn ($state) => $state->label())
                                ->color(fn ($state) => match ($state) {
                                    FindingStatus::Open => 'danger',
                                    FindingStatus::SubmittedForVerification => 'warning',
                                    FindingStatus::Verified => 'success',
                                    FindingStatus::Rejected => 'danger',
                                }),
                            TextEntry::make('created_at')->label('Waktu')->dateTime('d M Y H:i'),
                        ])
                        ->columns(5),
                ])
                ->visible(fn ($record) => $record->findings()->exists()),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_kim')
                ->label('Unduh KIM')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->visible(fn (): bool => $this->record->kimCard()->exists())
                ->url(fn (): string => route('kim.download', $this->record->kimCard)),
            Action::make('submit')
                ->label('Kirim Pengajuan')
                ->color('primary')
                ->icon('heroicon-o-paper-airplane')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status === ApplicationStatus::Draft)
                ->action(function (): void {
                    try {
                        app(KimApplicationService::class)->submit($this->record, auth()->user());
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title('Pengajuan gagal dikirim')
                            ->body(collect($e->errors())->flatten()->first())
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Pengajuan berhasil dikirim')
                        ->success()
                        ->send();
                }),
        ];
    }
}
