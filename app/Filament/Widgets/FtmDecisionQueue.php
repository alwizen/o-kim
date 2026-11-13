<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\DispensationRequests\DispensationRequestResource;
use App\Models\FindingDispensationRequest;
use App\Services\Audit\ActivityLogger;
use App\Services\Finding\DispensationService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Antrean keputusan FTM: permohonan dispensasi + approval akhir.
 *
 * Widget ini fokus pada permohonan dispensasi yang belum diputuskan.
 */
class FtmDecisionQueue extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Antrean Keputusan Saya';

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('ftm') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                FindingDispensationRequest::query()
                    ->with(['finding.application.vehicle', 'finding.application.transporter', 'requester'])
                    ->where('status', 'pending')
            )
            ->description('Permohonan KIM Sementara dari transportir yang menunggu keputusan Anda.')
            ->defaultSort('created_at', 'asc')
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('finding.application.application_number')
                    ->label('No. Pengajuan')
                    ->searchable(),
                TextColumn::make('finding.application.vehicle.registration_number')
                    ->label('NOPOL')
                    ->badge(),
                TextColumn::make('finding.department')
                    ->label('Fungsi')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('finding.description')
                    ->label('Temuan')
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('reason')
                    ->label('Alasan Permohonan')
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label('Diajukan')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Setujui Pengajuan KIM Sementara')
                    ->schema([
                        DatePicker::make('due_date')
                            ->label('Batas Perbaikan')
                            ->required()
                            ->minDate(Carbon::today()->addDay())
                            ->native(false),
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
                            $reason = app(ActivityLogger::class)->firstError($e);

                            app(ActivityLogger::class)->failureFromValidation(
                                'dispensation',
                                $record->finding?->application,
                                $e,
                                ['action' => 'approve_request', 'request_id' => $record->id],
                            );

                            Notification::make()
                                ->title('Gagal menyetujui')
                                ->body($reason)
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Dispensasi disetujui')
                            ->success()
                            ->send();
                    }),
                Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
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
                            $reason = app(ActivityLogger::class)->firstError($e);

                            app(ActivityLogger::class)->failureFromValidation(
                                'dispensation',
                                $record->finding?->application,
                                $e,
                                ['action' => 'reject_request', 'request_id' => $record->id],
                            );

                            Notification::make()
                                ->title('Gagal menolak')
                                ->body($reason)
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Pengajuan ditolak')
                            ->success()
                            ->send();
                    }),
                Action::make('open_queue')
                    ->label('Buka Menu')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(DispensationRequestResource::getUrl('index')),
            ])
            ->emptyStateHeading('Tidak ada permohonan menunggu')
            ->emptyStateDescription('Semua permohonan dispensasi sudah diputuskan.');
    }
}
