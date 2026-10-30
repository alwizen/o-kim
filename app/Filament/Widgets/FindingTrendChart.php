<?php

namespace App\Filament\Widgets;

use App\Models\Finding;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Tren jumlah temuan pemeriksaan per bulan.
 *
 * Periode dapat dipilih lewat dropdown (1 bulan, 3 bulan, 6 bulan, 1 tahun).
 */
class FindingTrendChart extends ChartWidget
{
    /** Pilihan periode (label => jumlah bulan). */
    public const PERIODS = [
        '1' => '1 Bulan',
        '3' => '3 Bulan',
        '6' => '6 Bulan',
        '12' => '1 Tahun',
    ];

    public const DEFAULT_PERIOD = '6';

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 1;

    protected ?string $heading = 'Tren Temuan';

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

        $counts = Finding::query()
            ->where('created_at', '>=', $start)
            ->get(['created_at'])
            ->groupBy(fn (Finding $finding): string => $finding->created_at->format('Y-m'))
            ->map(fn ($group): int => $group->count());

        $labels = [];
        $data = [];

        for ($i = 0; $i < $months; $i++) {
            $month = $start->copy()->addMonths($i);

            $labels[] = $months > 6
                ? $month->translatedFormat('M')
                : $month->translatedFormat('M Y');
            $data[] = (int) ($counts[$month->format('Y-m')] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Temuan',
                    'data' => $data,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.6)',
                    'borderColor' => 'rgb(239, 68, 68)',
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

        return 'Jumlah temuan pemeriksaan — '.$label.' terakhir';
    }
}
