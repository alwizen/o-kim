<?php

namespace App\Filament\Resources\DispensationRequests\Tables;

use App\Enums\DispensationRequestStatus;
use App\Models\FindingDispensationRequest;
use App\Services\Kim\KimIssuanceService;
use App\Services\Finding\DispensationService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class DispensationRequestsTable
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
                TextColumn::make('finding.application.application_number')
                    ->label('No. Pengajuan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('finding.application.transporter.company_name')
                    ->label('Perusahaan')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('finding.application.vehicle.registration_number')
                    ->label('NOPOL')
                    ->badge()
                    ->searchable(),
                // TextColumn::make('finding.department')
                //     ->label('Fungsi')
                //     ->badge()
                //     ->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('finding.description')
                    ->label('Temuan')
                    ->wrap()
                    ->limit(120),
                TextColumn::make('reason')
                    ->label('Alasan Permohonan')
                    ->wrap()
                    ->limit(160),
                TextColumn::make('due_date')
                    ->label('Batas Perbaikan')
                    ->date('d M Y')
                    ->placeholder('—'),
                TextColumn::make('decider.name')
                    ->label('Diputuskan Oleh')
                    ->placeholder('—'),
                TextColumn::make('decided_at')
                    ->label('Diputuskan Pada')
                    ->dateTime('d M Y H:i')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Diajukan Pada')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn(DispensationRequestStatus $state): string => $state->label())
                    ->color(fn(DispensationRequestStatus $state): string => $state->color())
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(DispensationRequestStatus::cases())
                        ->mapWithKeys(fn (DispensationRequestStatus $s) => [$s->value => $s->label()]))
                    // ->default(DispensationRequestStatus::Pending->value),
            ])
            ->defaultSort('created_at', 'asc')
            ->recordActions([
                Action::make('approve')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(FindingDispensationRequest $record): bool => $record->status->isPending())
                    ->requiresConfirmation()
                    ->modalHeading('Setujui Pengajuan KIM Sementara')
                    ->modalDescription('Tetapkan batas waktu perbaikan. KIM sementara akan berlaku sampai tanggal tersebut.')
                    ->schema([
                        DatePicker::make('due_date')
                            ->label('Batas Perbaikan')
                            ->required()
                            ->minDate(now()->addDay())
                            // ->native(false)
                            ->helperText('Dispensasi berlaku sampai akhir tanggal ini.'),
                        Textarea::make('notes')
                            ->label('Catatan (opsional)')
                            ->rows(2)
                            ->maxLength(1000),
                    ])
                    ->action(function (FindingDispensationRequest $record, array $data): void {
                        try {
                            app(DispensationService::class)->approveRequest(
                                $record,
                                auth()->user(),
                                $data['due_date'],
                                $data['notes'] ?? null,
                            );
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Gagal menyetujui')
                                ->body(collect($e->errors())->flatten()->first())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Dispensasi disetujui')
                            ->body('Transportir telah diberitahu batas perbaikannya.')
                            ->success()
                            ->send();
                    }),
                Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn(FindingDispensationRequest $record): bool => $record->status->isPending())
                    ->modalHeading('Tolak Pengajuan KIM Sementara')
                    ->schema([
                        Textarea::make('decision_notes')
                            ->label('Alasan Penolakan')
                            ->required()
                            ->minLength(10)
                            ->maxLength(1000)
                            ->rows(3),
                    ])
                    ->action(function (FindingDispensationRequest $record, array $data): void {
                        try {
                            app(DispensationService::class)->rejectRequest(
                                $record,
                                auth()->user(),
                                $data['decision_notes'],
                            );
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Gagal menolak')
                                ->body(collect($e->errors())->flatten()->first())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Pengajuan ditolak')
                            ->body('Transportir telah diberitahu alasannya.')
                            ->success()
                            ->send();
                    }),
                Action::make('issue_provisional')
                    ->label('Terbitkan KIM Sementara')
                    ->icon('heroicon-o-identification')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Terbitkan KIM Sementara')
                    ->modalDescription('Approval FTM dicatat dan KIM sementara langsung diterbitkan untuk kendaraan ini.')
                    ->visible(fn(FindingDispensationRequest $record): bool => $record->status === DispensationRequestStatus::Approved
                        && $record->finding?->application?->canIssueProvisional())
                    ->action(function (FindingDispensationRequest $record): void {
                        $application = $record->finding?->application;

                        if ($application === null) {
                            Notification::make()
                                ->title('Penerbitan gagal')
                                ->body('Pengajuan tidak ditemukan.')
                                ->danger()
                                ->send();

                            return;
                        }

                        try {
                            app(KimIssuanceService::class)->issueProvisional($application, auth()->user());
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Penerbitan gagal')
                                ->body(collect($e->errors())->flatten()->first())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('KIM sementara diterbitkan')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Tidak ada pengajuan KIM Sementara')
            ->emptyStateDescription('Semua permohonan dispensasi dari transportir sudah diputuskan.');
    }
}
