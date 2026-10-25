<?php

namespace App\Filament\Resources\NotificationSettings\Pages;

use App\Enums\NotificationEvent;
use App\Filament\Resources\NotificationSettings\NotificationSettingResource;
use App\Services\Notification\NotificationSettingsService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditNotificationSetting extends EditRecord
{
    protected static string $resource = NotificationSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resetDefault')
                ->label('Kembalikan ke Bawaan')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Kembalikan pengaturan email ini ke role bawaan sistem.')
                ->visible(fn (): bool => ! (NotificationEvent::tryFrom($this->record->event_key)?->locked() ?? true))
                ->action(function (): void {
                    $event = NotificationEvent::tryFrom($this->record->event_key);

                    if ($event === null) {
                        return;
                    }

                    $this->record->update([
                        'mail_enabled' => true,
                        'roles' => $event->defaultRoles(),
                    ]);

                    app(NotificationSettingsService::class)->flush();

                    Notification::make()
                        ->title('Pengaturan dikembalikan ke bawaan')
                        ->success()
                        ->send();

                    $this->fillForm();
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
