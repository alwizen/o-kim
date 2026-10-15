<?php

namespace App\Filament\Resources\Transporters\Tables;

use App\Enums\AccountStatus;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TransportersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama PIC')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('transporters.company_name')
                    ->label('Perusahaan')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('account_status')
                    ->label('Status Akun')
                    ->badge()
                    ->formatStateUsing(fn (AccountStatus $state): string => $state->label())
                    ->color(fn (AccountStatus $state): string => match ($state) {
                        AccountStatus::Pending => 'warning',
                        AccountStatus::Approved => 'success',
                        AccountStatus::Rejected => 'danger',
                        AccountStatus::Suspended => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Terdaftar')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('account_status')
                    ->label('Status Akun')
                    ->options(collect(AccountStatus::cases())->mapWithKeys(
                        fn (AccountStatus $s) => [$s->value => $s->label()]
                    )),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
