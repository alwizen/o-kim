<?php

namespace App\Filament\Resources\NotificationSettings\Tables;

use App\Enums\InternalRole;
use App\Enums\NotificationEvent;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class NotificationSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->paginated([50, 100])
            ->columns([
                TextColumn::make('event_key')
                    ->label('Notifikasi')
                    ->formatStateUsing(fn (string $state): string => NotificationEvent::tryFrom($state)?->label() ?? $state)
                    ->description(fn ($record): string => $record->event_key)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('group')
                    ->label('Grup')
                    ->badge()
                    ->state(fn ($record): string => NotificationEvent::tryFrom($record->event_key)?->group() ?? '—')
                    ->color(fn (string $state): string => match ($state) {
                        NotificationEvent::GROUP_TRANSPORTER => 'info',
                        NotificationEvent::GROUP_INTERNAL => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('mail_enabled')
                    ->label('Email')
                    ->badge()
                    ->state(fn ($record): string => NotificationEvent::tryFrom($record->event_key)?->locked()
                        ? 'Selalu aktif'
                        : ($record->mail_enabled ? 'Aktif' : 'Nonaktif'))
                    ->color(fn (string $state): string => match ($state) {
                        'Aktif' => 'success',
                        'Nonaktif' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('roles')
                    ->label('Penerima (Role)')
                    ->badge()
                    ->state(fn ($record): array => collect($record->roleNames())
                        ->map(fn (string $role): string => InternalRole::tryFrom($role)?->label()
                            ?? str($role)->replace('_', ' ')->title()->toString())
                        ->all())
                    ->placeholder('Belum dipilih')
                    ->wrap(),
                TextColumn::make('updated_at')
                    ->label('Terakhir Diubah')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('group')
                    ->label('Grup')
                    ->options(NotificationEvent::groupOptions())
                    ->query(function ($query, array $data) {
                        $events = collect(NotificationEvent::cases())
                            ->filter(fn (NotificationEvent $event) => $data['value'] === null
                                || $data['value'] === ''
                                || $event->group() === $data['value'])
                            ->map(fn (NotificationEvent $event) => $event->value)
                            ->all();

                        return $query->whereIn('event_key', $events);
                    }),
                TernaryFilter::make('mail_enabled')
                    ->label('Status Email')
                    ->trueLabel('Aktif saja')
                    ->falseLabel('Nonaktif saja')
                    ->placeholder('Semua'),
            ])
            ->defaultSort('event_key')
            ->recordActions([
                EditAction::make()->label('Atur'),
            ]);
    }
}
