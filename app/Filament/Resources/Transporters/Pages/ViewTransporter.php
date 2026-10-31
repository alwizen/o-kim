<?php

namespace App\Filament\Resources\Transporters\Pages;

use App\Actions\Registration\ApproveTransporterRegistration;
use App\Actions\Registration\RejectTransporterRegistration;
use App\Enums\AccountStatus;
use App\Filament\Resources\Transporters\TransporterResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewTransporter extends ViewRecord
{
    protected static string $resource = TransporterResource::class;

    protected function getHeaderActions(): array
    {
        // Tombol verifikasi hanya untuk pendaftaran yang sudah mengirim profil perusahaan.
        // Daftar sudah disaring di resource; ini menjaga akses langsung via URL record.
        $isReviewable = fn (): bool => $this->record->account_status === AccountStatus::Pending
            && $this->record->transporters()->whereNotNull('profile_submitted_at')->exists()
            && auth()->user()->can('review_transporter_registrations');

        return [
            Action::make('approve')
                ->label('Setujui Pendaftaran')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                ->visible($isReviewable)
                ->action(function (): void {
                    app(ApproveTransporterRegistration::class)->execute($this->record, auth()->user());

                    Notification::make()
                        ->title('Pendaftaran disetujui')
                        ->success()
                        ->send();
                }),
            Action::make('reject')
                ->label('Tolak Pendaftaran')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->visible($isReviewable)
                ->schema([
                    Textarea::make('reason')
                        ->label('Alasan Penolakan')
                        ->required()
                        ->maxLength(1000),
                ])
                ->action(function (array $data): void {
                    app(RejectTransporterRegistration::class)->execute(
                        $this->record,
                        auth()->user(),
                        $data['reason'],
                    );

                    Notification::make()
                        ->title('Pendaftaran ditolak')
                        ->success()
                        ->send();
                }),
        ];
    }
}
