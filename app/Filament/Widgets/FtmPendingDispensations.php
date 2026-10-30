<?php

namespace App\Filament\Widgets;

use App\Enums\FindingStatus;
use App\Models\Finding;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Statistik ringkas KIM sementara dan dispensasi untuk FTM.
 *
 * Melengkapi FtmDecisionQueue (tabel) dengan angka agregat.
 */
class FtmPendingDispensations extends StatsOverviewWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('ftm') ?? false;
    }

    protected function getStats(): array
    {
        $active = Finding::query()
            ->where('status', FindingStatus::Dispensation->value)
            ->count();

        $lapsed = Finding::query()
            ->where('status', FindingStatus::Dispensation->value)
            ->whereNotNull('dispensation_due_date')
            ->whereDate('dispensation_due_date', '<', now()->toDateString())
            ->count();

        $dueThisMonth = Finding::query()
            ->where('status', FindingStatus::Dispensation->value)
            ->whereNotNull('dispensation_due_date')
            ->whereBetween('dispensation_due_date', [
                now()->startOfMonth()->toDateString(),
                now()->endOfMonth()->toDateString(),
            ])
            ->count();

        return [
            Stat::make('Dispensasi Aktif', $active)
                ->description('Temuan dalam penundaan perbaikan')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning')
                ->icon('heroicon-o-clock'),

            Stat::make('Berakhir Bulan Ini', $dueThisMonth)
                ->description(now()->translatedFormat('F Y'))
                ->descriptionIcon('heroicon-m-calendar')
                ->color($dueThisMonth > 0 ? 'info' : 'gray')
                ->icon('heroicon-o-calendar'),

            Stat::make('Sudah Melewati Batas', $lapsed)
                ->description($lapsed > 0 ? 'Perlu tindakan segera' : 'Tidak ada')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($lapsed > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-exclamation-triangle'),
        ];
    }
}
