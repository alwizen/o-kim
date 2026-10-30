<?php

namespace App\Filament\Widgets;

use App\Models\KimApplication;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Tren jumlah pengajuan KIM per bulan.
 *
 * Periode dapat dipilih lewat dropdown (1 bulan, 3 bulan, 6 bulan, 1 tahun).
 * Pengelompokan dilakukan di PHP agar portabel (MySQL & SQLite) tanpa fungsi
 * SQL vendor-spesifik.
 */
class ApplicationTrendChart extends ChartWidget
{
    /** Pilihan periode (label => jumlah bulan). */
    public const PERIODS = [
        '1' => '1 Bulan',
        '3' => '3 Bulan',
        '6' => '6 Bulan',
        '12' => '1 Tahun',
    ];

    public const DEFAULT_PERIOD = '6';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 1;

    protected ?string $heading = 'Tren Pengajuan';

    protected ?string $maxHeight = '260px';

    public ?string $filter = self::DEFAULT_PERIOD;

    public static function canView(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $user->hasRole('super_admin') || $user->hasRole('ftm');
    }

    protected function getFilters(): ?array
    {
        return self::PERIODS;
    }

    protected function getData(): array
    {
        $months = (int) ($this->filter ?? self::DEFAULT_PERIOD);
        $start = Carbon::today()->startOfMonth()->subMonths($months - 1);

        $counts = KimApplication::query()
            ->where('created_at', '>=', $start)
            ->get(['created_at'])
            ->groupBy(fn (KimApplication $application): string => $application->created_at->format('Y-m'))
            ->map(fn ($group): int => $group->count());

        $labels = [];
        $data = [];

        for ($i = 0; $i < $months; $i++) {
            $month = $start->copy()->addMonths($i);
            $key = $month->format('Y-m');

            // Label diringkas bila periode panjang agar sumbu X tetap terbaca.
            $labels[] = $months > 6
                ? $month->translatedFormat('M')
                : $month->translatedFormat('M Y');
            $data[] = (int) ($counts[$key] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Pengajuan',
                    'data' => $data,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.6)',
                    'borderColor' => 'rgb(59, 130, 246)',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    public function getDescription(): ?string
    {
        $label = self::PERIODS[$this->filter ?? self::DEFAULT_PERIOD] ?? '';

        return 'Jumlah pengajuan KIM — '.$label.' terakhir';
    }
}
