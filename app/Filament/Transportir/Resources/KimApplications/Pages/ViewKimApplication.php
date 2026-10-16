<?php

namespace App\Filament\Transportir\Resources\KimApplications\Pages;

use App\Enums\ApplicationStatus;
use App\Filament\Transportir\Resources\KimApplications\KimApplicationResource;
use App\Services\Application\KimApplicationService;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

class ViewKimApplication extends ViewRecord
{
    protected static string $resource = KimApplicationResource::class;

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
                ->schema([
                    TextEntry::make('findings_count')
                        ->label('Jumlah Temuan')
                        ->state(fn ($record) => $record->findings()->count()),
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
