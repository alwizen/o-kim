<?php

namespace App\Filament\Resources\KimApplications\Tables;

use App\Enums\ApplicationStatus;
use App\Models\KimApplication;
use App\Notifications\InspectionScheduled;
use App\Support\AppNotifier;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class KimApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('application_number')
                    ->label('No. Pengajuan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('transporter.company_name')
                    ->label('Perusahaan')
                    ->searchable(),
                TextColumn::make('vehicle.registration_number')
                    ->label('NOPOL')
                    ->searchable(),
                TextColumn::make('application_type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn (ApplicationStatus $state) => match ($state) {
                        ApplicationStatus::Draft => 'gray',
                        ApplicationStatus::Submitted, ApplicationStatus::Inspection => 'info',
                        ApplicationStatus::RevisionRequired, ApplicationStatus::PendingApproval => 'warning',
                        ApplicationStatus::Approved, ApplicationStatus::Issued => 'success',
                        ApplicationStatus::Rejected, ApplicationStatus::Cancelled => 'danger',
                    })
                    ->sortable(),
                TextColumn::make('findings_count')
                    ->label('Temuan')
                    ->counts('findings'),
                TextColumn::make('inspection_scheduled_at')
                    ->label('Jadwal Periksa')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Belum dijadwalkan')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'warning')
                    ->sortable(),
                TextColumn::make('submitted_at')
                    ->label('Dikirim')
                    ->dateTime('d M Y')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(ApplicationStatus::cases())->mapWithKeys(
                        fn (ApplicationStatus $s) => [$s->value => $s->label()]
                    )),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->recordActions([
                Action::make('schedule')
                    ->label('Tetapkan Tanggal')
                    ->icon('heroicon-o-calendar-days')
                    ->color('primary')
                    ->visible(fn (KimApplication $record): bool => in_array($record->status, [
                        ApplicationStatus::Submitted,
                        ApplicationStatus::Inspection,
                    ], true) && auth()->user()->can('view_applications'))
                    ->schema([
                        DateTimePicker::make('inspection_scheduled_at')
                            ->label('Tanggal & Waktu Pelaksanaan Pemeriksaan')
                            ->required()
                            ->seconds(false)
                            ->native(false)
                            ->default(fn (KimApplication $record) => $record->inspection_scheduled_at),
                    ])
                    ->fillForm(fn (KimApplication $record): array => [
                        'inspection_scheduled_at' => $record->inspection_scheduled_at,
                    ])
                    ->action(function (KimApplication $record, array $data): void {
                        $record->update([
                            'inspection_scheduled_at' => $data['inspection_scheduled_at'],
                            'inspection_scheduled_by' => auth()->id(),
                        ]);

                        AppNotifier::notifyTransporter(
                            $record->transporter_id,
                            new InspectionScheduled(
                                $record->application_number,
                                $record->vehicle->registration_number,
                                Carbon::parse($data['inspection_scheduled_at'])->translatedFormat('d M Y H:i'),
                            ),
                        );

                        Notification::make()
                            ->title('Jadwal pemeriksaan ditetapkan')
                            ->body('Petugas dapat mulai mengisi checklist.')
                            ->success()
                            ->send();
                    }),
                ViewAction::make(),
            ]);
    }
}
