<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\AccountStatus;
use App\Enums\InternalRole;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => InternalRole::tryFrom($state)?->label() ?? $state)
                    ->color('info'),
                TextColumn::make('account_status')
                    ->label('Status Akun')
                    ->badge()
                    ->formatStateUsing(fn (AccountStatus $state): string => $state->label())
                    ->color(fn (AccountStatus $state): string => match ($state) {
                        AccountStatus::Approved => 'success',
                        AccountStatus::Pending => 'warning',
                        AccountStatus::Rejected => 'danger',
                        AccountStatus::Suspended => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Terakhir Diubah')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label('Role')
                    ->relationship('roles', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => InternalRole::tryFrom($record->name)?->label() ?? $record->name)
                    ->preload(),
                SelectFilter::make('account_status')
                    ->label('Status Akun')
                    ->options(collect(AccountStatus::cases())->mapWithKeys(
                        fn (AccountStatus $s) => [$s->value => $s->label()]
                    )),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
