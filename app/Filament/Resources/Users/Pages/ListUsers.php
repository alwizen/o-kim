<?php

namespace App\Filament\Resources\Users\Pages;

use App\Actions\User\ActivateUser;
use App\Actions\User\DeactivateUser;
use App\Enums\AccountStatus;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah Pengguna'),
        ];
    }

    public function table(Table $table): Table
    {
        return parent::table($table)->recordActions([
            EditAction::make(),
            Action::make('deactivate')
                ->label('Nonaktifkan')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn ($record): bool => $record->account_status === AccountStatus::Approved
                    && ! $record->is(auth()->user()))
                ->action(function ($record): void {
                    try {
                        app(DeactivateUser::class)->execute($record, auth()->user());
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title('Gagal menonaktifkan')
                            ->body(collect($e->errors())->flatten()->first())
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Akun dinonaktifkan')
                        ->success()
                        ->send();
                }),
            Action::make('activate')
                ->label('Aktifkan')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn ($record): bool => $record->account_status !== AccountStatus::Approved)
                ->action(function ($record): void {
                    app(ActivateUser::class)->execute($record);

                    Notification::make()
                        ->title('Akun diaktifkan')
                        ->success()
                        ->send();
                }),
        ]);
    }
}
