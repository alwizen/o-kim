<?php

namespace App\Filament\Widgets;

use App\Enums\ApplicationStatus;
use App\Filament\Resources\KimApplications\KimApplicationResource;
use App\Models\KimApplication;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Carbon;

/**
 * Pengajuan dengan jadwal pemeriksaan hari ini (atau yang terlewat).
 *
 * Membantu admin memantau pelaksanaan pemeriksaan.
 */
class InspectionsDueToday extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Jadwal Pemeriksaan Hari Ini';

    public static function canView(): bool
    {
        $user = auth()->user();

        if ($user === null || ! $user->can('schedule_inspection')) {
            return false;
        }

        return $user->hasRole('super_admin') || $user->hasRole('admin_verifikasi');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                KimApplication::query()
                    ->with(['vehicle', 'transporter'])
                    ->whereIn('status', [
                        ApplicationStatus::Submitted->value,
                        ApplicationStatus::Inspection->value,
                        ApplicationStatus::RevisionRequired->value,
                    ])
                    ->whereNotNull('inspection_scheduled_at')
                    ->whereDate('inspection_scheduled_at', '<=', Carbon::today()->toDateString())
            )
            ->description('Termasuk jadwal yang sudah terlewat dan belum selesai diperiksa.')
            ->defaultSort('inspection_scheduled_at', 'asc')
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('application_number')
                    ->label('No. Pengajuan')
                    ->searchable(),
                TextColumn::make('vehicle.registration_number')
                    ->label('NOPOL')
                    ->badge(),
                TextColumn::make('transporter.company_name')
                    ->label('Perusahaan')
                    ->limit(28)
                    ->wrap(),
                TextColumn::make('inspection_scheduled_at')
                    ->label('Jadwal')
                    ->dateTime('d M Y H:i')
                    ->badge()
                    ->color(fn (KimApplication $record): string => $record->inspection_scheduled_at
                        && $record->inspection_scheduled_at->isPast() ? 'danger' : 'info')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label()),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Buka')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (KimApplication $record): string => KimApplicationResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading('Tidak ada jadwal hari ini')
            ->emptyStateDescription('Belum ada pemeriksaan yang dijadwalkan hari ini.');
    }
}
