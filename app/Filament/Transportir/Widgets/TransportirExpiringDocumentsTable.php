<?php

namespace App\Filament\Transportir\Widgets;

use App\Services\Document\DocumentExpiryService;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Carbon;

class TransportirExpiringDocumentsTable extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Dokumen Kendaraan Perlu Diperbarui';

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('transportir') ?? false;
    }

    public function table(Table $table): Table
    {
        $transporterIds = auth()->user()->transporters()->pluck('transporters.id')->all();

        return $table
            ->query(app(DocumentExpiryService::class)->expiringDocumentsQuery($transporterIds))
            ->description('Dokumen kendaraan Anda yang kedaluwarsa atau akan kedaluwarsa dalam '
                .DocumentExpiryService::WARNING_THRESHOLD_DAYS.' hari')
            ->columns([
                TextColumn::make('vehicle.registration_number')
                    ->label('NOPOL')
                    ->searchable(),
                TextColumn::make('documentType.name')
                    ->label('Jenis Dokumen')
                    ->badge(),
                TextColumn::make('expires_at')
                    ->label('Kedaluwarsa')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('days_left')
                    ->label('Sisa Waktu')
                    ->badge()
                    ->state(fn ($record) => $this->formatDaysLeft($record->expires_at))
                    ->color(fn ($record) => $this->daysLeftColor($record->expires_at)),
                TextColumn::make('verification_status')
                    ->label('Verifikasi')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => match ($state->value) {
                        'verified' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    }),
            ])
            ->defaultSort('expires_at', 'asc')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->recordActions([
                Action::make('perbarui')
                    ->label('Perbarui')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->url(fn ($record) => '/transportir/vehicles/'.$record->vehicle_id.'/edit'),
            ])
            ->emptyStateHeading('Semua dokumen aman')
            ->emptyStateDescription('Tidak ada dokumen yang mendekati kedaluwarsa.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }

    private function formatDaysLeft(Carbon $expiresAt): string
    {
        $days = DocumentExpiryService::daysUntil($expiresAt);

        if ($days < 0) {
            return 'Lewat '.abs($days).' hari';
        }

        if ($days === 0) {
            return 'Hari ini';
        }

        return $days.' hari lagi';
    }

    private function daysLeftColor(Carbon $expiresAt): string
    {
        $days = DocumentExpiryService::daysUntil($expiresAt);

        return match (true) {
            $days < 0 => 'danger',
            $days <= 14 => 'danger',
            $days <= 30 => 'warning',
            default => 'info',
        };
    }
}
