<?php

namespace App\Filament\Resources\Findings\Actions;

use App\Enums\ApplicationStatus;
use App\Enums\FindingStatus;
use App\Models\Finding;
use App\Models\User;
use App\Services\Finding\DispensationService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

class DispensationActions
{
    public static function canDispense(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->can('dispensate_findings')
            && $user->hasRole('ftm');
    }

    public static function canGrant(Finding $finding): bool
    {
        return self::canDispense()
            && in_array($finding->status, [FindingStatus::Open, FindingStatus::Rejected], true)
            && in_array($finding->application?->status, [
                ApplicationStatus::Inspection,
                ApplicationStatus::RevisionRequired,
            ], true);
    }

    public static function grant(): Action
    {
        return Action::make('dispensation_grant')
            ->label('Beri Dispensasi (FTM)')
            ->icon('heroicon-o-clock')
            ->color('warning')
            ->visible(fn (Finding $record): bool => self::canGrant($record))
            ->schema([
                DatePicker::make('dispensation_due_date')
                    ->label('Batas Perbaikan')
                    ->required()
                    ->minDate(now()->addDay())
                    ->native(false),
                Textarea::make('dispensation_reason')
                    ->label('Alasan Dispensasi')
                    ->required()
                    ->minLength(10)
                    ->rows(3)
                    ->maxLength(1000),
            ])
            ->action(function (Finding $record, array $data): void {
                self::run(fn () => app(DispensationService::class)->grant(
                    $record,
                    auth()->user(),
                    $data['dispensation_due_date'],
                    $data['dispensation_reason'],
                ), 'Dispensasi diberikan');
            });
    }

    public static function extend(): Action
    {
        return Action::make('dispensation_extend')
            ->label('Perpanjang Dispensasi')
            ->icon('heroicon-o-arrow-path')
            ->color('info')
            ->visible(fn (Finding $record): bool => self::canDispense()
                && $record->status === FindingStatus::Dispensation
                && ! $record->isDispensationExpired())
            ->schema([
                DatePicker::make('dispensation_due_date')
                    ->label('Batas Perbaikan Baru')
                    ->required()
                    ->minDate(now()->addDay())
                    ->native(false)
                    ->default(fn (Finding $record) => $record->dispensation_due_date),
                Textarea::make('notes')
                    ->label('Catatan (opsional)')
                    ->rows(2)
                    ->maxLength(1000),
            ])
            ->action(function (Finding $record, array $data): void {
                self::run(fn () => app(DispensationService::class)->extend(
                    $record,
                    auth()->user(),
                    $data['dispensation_due_date'],
                    $data['notes'] ?? null,
                ), 'Dispensasi diperpanjang');
            });
    }

    public static function updateReason(): Action
    {
        return Action::make('dispensation_reason')
            ->label('Ubah Alasan Dispensasi')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->visible(fn (Finding $record): bool => self::canDispense()
                && $record->status === FindingStatus::Dispensation)
            ->schema([
                Textarea::make('dispensation_reason')
                    ->label('Alasan Dispensasi')
                    ->required()
                    ->minLength(10)
                    ->rows(3)
                    ->maxLength(1000)
                    ->default(fn (Finding $record) => $record->dispensation_reason),
                Textarea::make('notes')
                    ->label('Catatan Perubahan (opsional)')
                    ->rows(2)
                    ->maxLength(1000),
            ])
            ->action(function (Finding $record, array $data): void {
                self::run(fn () => app(DispensationService::class)->updateReason(
                    $record,
                    auth()->user(),
                    $data['dispensation_reason'],
                    $data['notes'] ?? null,
                ), 'Alasan dispensasi diperbarui');
            });
    }

    public static function revoke(): Action
    {
        return Action::make('dispensation_revoke')
            ->label('Cabut Dispensasi')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Temuan kembali wajib diperbaiki. KIM sementara (bila sudah terbit) akan langsung dicabut.')
            ->visible(fn (Finding $record): bool => self::canDispense()
                && $record->status === FindingStatus::Dispensation)
            ->schema([
                Textarea::make('notes')
                    ->label('Catatan Pencabutan (opsional)')
                    ->rows(2)
                    ->maxLength(1000),
            ])
            ->action(function (Finding $record, array $data): void {
                self::run(fn () => app(DispensationService::class)->revoke(
                    $record,
                    auth()->user(),
                    $data['notes'] ?? null,
                ), 'Dispensasi dicabut');
            });
    }

    /**
     * @param  callable(): mixed  $callback
     */
    private static function run(callable $callback, string $successTitle): void
    {
        try {
            $callback();
        } catch (ValidationException $e) {
            Notification::make()
                ->title('Proses gagal')
                ->body(collect($e->errors())->flatten()->first())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title($successTitle)
            ->success()
            ->send();
    }
}
