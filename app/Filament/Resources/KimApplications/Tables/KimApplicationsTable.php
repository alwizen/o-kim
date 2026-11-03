<?php

namespace App\Filament\Resources\KimApplications\Tables;

use App\Enums\ApplicationStatus;
use App\Filament\Resources\KimApplications\KimApplicationResource;
use App\Models\KimApplication;
use App\Notifications\InspectionScheduled;
use App\Notifications\InspectionScheduledForStaff;
use App\Services\Application\KimApplicationService;
use App\Support\AppNotifier;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

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
                TextColumn::make('transporter.company_name')
                    ->label('Perusahaan')
                    ->searchable(),
                TextColumn::make('vehicle.registration_number')
                    ->label('NOPOL')
                    ->searchable(),
                TextColumn::make('pending_documents_count')
                    ->label('Dokumen Pending')
                    ->state(fn(KimApplication $record): int => (int) ($record->vehicle?->pending_documents_count ?? 0))
                    ->badge()
                    ->placeholder('0')
                    ->color(fn($state) => $state > 0 ? 'warning' : 'success')
                    ->formatStateUsing(fn($state) => $state > 0 ? "{$state} pending" : 'Lengkap'),
                TextColumn::make('application_type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn($state) => $state->label()),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn($state) => $state->label())
                    ->color(fn(ApplicationStatus $state) => match ($state) {
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
                    ->color(fn($state) => $state ? 'success' : 'warning')
                    ->sortable(),
                TextColumn::make('submitted_at')
                    ->label('Diajukan Pada')
                    ->dateTime('d M Y')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(ApplicationStatus::cases())->mapWithKeys(
                        fn(ApplicationStatus $s) => [$s->value => $s->label()]
                    )),
                Filter::make('submitted_at')
                    ->label('Tanggal Pengajuan')
                    ->schema([
                        DatePicker::make('submitted_from')
                            ->label('Dari tanggal'),
                        DatePicker::make('submitted_until')
                            ->label('Sampai tanggal'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['submitted_from'] ?? null,
                            fn (Builder $query, string $date): Builder => $query
                                ->whereDate('submitted_at', '>=', $date),
                        )
                        ->when(
                            $data['submitted_until'] ?? null,
                            fn (Builder $query, string $date): Builder => $query
                                ->whereDate('submitted_at', '<=', $date),
                        ))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['submitted_from'] ?? null) {
                            $indicators[] = 'Diajukan dari '.Carbon::parse($data['submitted_from'])->translatedFormat('d M Y');
                        }

                        if ($data['submitted_until'] ?? null) {
                            $indicators[] = 'Diajukan sampai '.Carbon::parse($data['submitted_until'])->translatedFormat('d M Y');
                        }

                        return $indicators;
                    }),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->recordActions([
                ActionGroup::make([
                    Action::make('inspection_report')
                        ->label('Laporan Pemeriksaan')
                        ->icon('heroicon-o-printer')
                        ->color('gray')
                        ->visible(fn(KimApplication $record): bool => (bool) $record->inspection_results_exists)
                        ->url(fn(KimApplication $record): string => route('inspection-report.preview', $record))
                        ->openUrlInNewTab(),
                    Action::make('inspect')
                        ->label('Isi Checklist')
                        ->icon('heroicon-o-clipboard-document-check')
                        ->color('info')
                        ->visible(fn(KimApplication $record): bool => $record->isInspectionDue()
                            && in_array($record->status, [
                                ApplicationStatus::Submitted,
                                ApplicationStatus::Inspection,
                                ApplicationStatus::RevisionRequired,
                            ], true) && (
                                auth()->user()->can('inspect_hsse')
                                || auth()->user()->can('inspect_distribusi')
                                || auth()->user()->can('inspect_qq')
                            ))
                        ->url(fn(KimApplication $record): string => KimApplicationResource::getUrl('inspect', ['record' => $record])),
                    Action::make('schedule')
                        ->label(fn(KimApplication $record): string => $record->inspection_scheduled_at
                            ? 'Reschedule'
                            : 'Tetapkan Tanggal Pemeriksaan')
                        ->icon('heroicon-o-calendar-days')
                        ->color('primary')
                        ->visible(fn(KimApplication $record): bool => in_array($record->status, [
                            ApplicationStatus::Submitted,
                            ApplicationStatus::Inspection,
                        ], true) && auth()->user()->can('schedule_inspection'))
                        ->schema([
                            DateTimePicker::make('inspection_scheduled_at')
                                ->label('Tanggal & Waktu Pelaksanaan Pemeriksaan')
                                ->required()
                                ->seconds(false)
                                // ->native(false)
                                ->default(fn(KimApplication $record) => $record->inspection_scheduled_at),
                        ])
                        ->fillForm(fn(KimApplication $record): array => [
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

                            $scheduledAt = Carbon::parse($data['inspection_scheduled_at'])->translatedFormat('d M Y H:i');

                            AppNotifier::notifyTransporter(
                                $record->transporter_id,
                                new InspectionScheduled(
                                    $record->application_number,
                                    $record->vehicle->registration_number,
                                    $scheduledAt,
                                ),
                            );

                            AppNotifier::notifyStaffWithPermission(
                                'schedule_inspection',
                                new InspectionScheduledForStaff(
                                    $record->application_number,
                                    $record->vehicle->registration_number,
                                    $scheduledAt,
                                    $isReschedule,
                                    (string) $record->getRouteKey(),
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
                ])
                ->icon(Heroicon::OutlinedBars3),
            ]);
    }
}
