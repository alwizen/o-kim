<?php

namespace App\Filament\Transportir\Resources\KimApplications\Tables;

use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Filament\Transportir\Resources\KimApplications\Pages\CreateKimApplication;
use App\Models\KimApplication;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class KimApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('no')
                    ->label('No')
                    ->rowIndex()
                    ->alignCenter()
                    ->width(1)
                    ->grow(false),
                TextColumn::make('application_number')
                    ->label('No. Pengajuan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('vehicle.registration_number')
                    ->label('NOPOL')
                    ->searchable(),
                TextColumn::make('application_type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (ApplicationType $state) => $state->label())
                    ->color(fn (ApplicationType $state) => $state === ApplicationType::New ? 'info' : 'gray'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ApplicationStatus $state) => $state->label())
                    ->color(fn (ApplicationStatus $state) => match ($state) {
                        ApplicationStatus::Draft => 'gray',
                        ApplicationStatus::Submitted, ApplicationStatus::Inspection => 'info',
                        ApplicationStatus::RevisionRequired => 'warning',
                        ApplicationStatus::PendingApproval => 'warning',
                        ApplicationStatus::Approved, ApplicationStatus::Issued => 'success',
                        ApplicationStatus::Rejected, ApplicationStatus::Cancelled => 'danger',
                    })
                    ->sortable(),
                TextColumn::make('submitted_at')
                    ->label('Dikirim')
                    ->dateTime('d M Y H:i')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                // KIM sementara yang sudah tidak berlaku perlu diajukan ulang
                // melalui pengajuan Perpanjangan baru.
                Action::make('renew')
                    ->label('Ajukan Ulang')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (KimApplication $record): bool => $record->canBeRenewed())
                    ->url(fn (KimApplication $record): string => CreateKimApplication::getUrl([
                        'vehicle_id' => $record->vehicle_id,
                        'application_type' => ApplicationType::Renewal->value,
                    ])),
            ]);
    }
}
