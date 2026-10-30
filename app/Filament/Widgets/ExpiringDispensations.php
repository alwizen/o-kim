<?php

namespace App\Filament\Widgets;

use App\Enums\FindingStatus;
use App\Filament\Resources\Findings\FindingResource;
use App\Models\Finding;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Carbon;

/**
 * Temuan yang dispensasinya akan segera berakhir.
 *
 * Pencegahan dini agar KIM sementara tidak gugur otomatis karena batas
 * dispensasi terlewati.
 */
class ExpiringDispensations extends TableWidget
{
    private const WARNING_DAYS = 14;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Dispensasi Akan Berakhir';

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('ftm') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Finding::query()
                    ->with(['application.vehicle', 'application.transporter'])
                    ->where('status', FindingStatus::Dispensation->value)
                    ->whereNotNull('dispensation_due_date')
                    ->whereDate('dispensation_due_date', '>=', Carbon::today()->toDateString())
                    ->whereDate('dispensation_due_date', '<=', Carbon::today()->addDays(self::WARNING_DAYS)->toDateString())
            )
            ->description('Batas perbaikan ≤ '.self::WARNING_DAYS.' hari. Perpanjang atau pastikan perbaikan selesai sebelum KIM sementara gugur.')
            ->defaultSort('dispensation_due_date', 'asc')
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('application.application_number')
                    ->label('No. Pengajuan')
                    ->searchable(),
                TextColumn::make('application.vehicle.registration_number')
                    ->label('NOPOL')
                    ->badge(),
                TextColumn::make('department')
                    ->label('Fungsi')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('description')
                    ->label('Temuan')
                    ->limit(50)
                    ->wrap(),
                TextColumn::make('dispensation_due_date')
                    ->label('Batas Perbaikan')
                    ->date('d M Y')
                    ->badge()
                    ->color(fn (Finding $record): string => $record->dispensation_due_date
                        && $record->dispensation_due_date->diffInDays(Carbon::today()) <= 3
                            ? 'danger'
                            : 'warning')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Buka Temuan')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (Finding $record): string => FindingResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading('Tidak ada dispensasi mendesak')
            ->emptyStateDescription('Tidak ada dispensasi yang berakhir dalam '.self::WARNING_DAYS.' hari.');
    }
}
