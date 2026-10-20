<?php

namespace App\Filament\Resources\KimApplications\Tables;

use App\Enums\ApplicationStatus;
use App\Enums\DocumentVerificationStatus;
use App\Filament\Resources\KimApplications\KimApplicationResource;
use App\Models\KimApplication;
use App\Notifications\InspectionScheduled;
use App\Services\Application\KimApplicationService;
use App\Support\AppNotifier;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

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
                TextColumn::make('vehicle.documents_count')
                    ->label('Dokumen Pending')
                    ->state(function (KimApplication $record): int {
                        return $record->vehicle->documents()
                            ->whereNull('replaced_by_id')
                            ->whereHas('documentType', fn ($query) => $query->where('is_required', true)->where('is_active', true))
                            ->where('verification_status', DocumentVerificationStatus::Pending)
                            ->count();
                    })
                    ->badge()
                    ->placeholder('0')
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success')
                    ->formatStateUsing(fn ($state) => $state > 0 ? "{$state} pending" : 'Lengkap'),
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
                Action::make('inspect')
                    ->label('Isi Checklist')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('info')
                    ->visible(fn (KimApplication $record): bool => $record->isInspectionDue()
                        && in_array($record->status, [
                            ApplicationStatus::Submitted,
                            ApplicationStatus::Inspection,
                            ApplicationStatus::RevisionRequired,
                        ], true) && (
                            auth()->user()->can('inspect_hsse')
                            || auth()->user()->can('inspect_distribusi')
                            || auth()->user()->can('inspect_qq')
                        ))
                    ->url(fn (KimApplication $record): string => KimApplicationResource::getUrl('inspect', ['record' => $record])),
                Action::make('schedule')
                    ->label(fn (KimApplication $record): string => $record->inspection_scheduled_at
                        ? 'Reschedule'
                        : 'Tetapkan Tanggal Pemeriksaan')
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
                            // ->native(false)
                            ->default(fn (KimApplication $record) => $record->inspection_scheduled_at),
                    ])
                    ->fillForm(fn (KimApplication $record): array => [
                        'inspection_scheduled_at' => $record->inspection_scheduled_at,
                    ])
                    ->action(function (KimApplication $record, array $data): void {
                        // Dokumen wajib harus terverifikasi sebelum pemeriksaan dijadwalkan.
                        try {
                            app(KimApplicationService::class)
                                ->ensureRequiredDocumentsVerified($record->vehicle);
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Dokumen belum terverifikasi')
                                ->body(collect($e->errors())->flatten()->first())
                                ->danger()
                                ->send();

                            return;
                        }

                        $isReschedule = $record->inspection_scheduled_at !== null;

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
                            ->title($isReschedule
                                ? 'Jadwal pemeriksaan diperbarui'
                                : 'Jadwal pemeriksaan ditetapkan')
                            ->body('Petugas dapat mulai mengisi checklist.')
                            ->success()
                            ->send();
                    }),
                ViewAction::make(),
            ]);
    }
}
