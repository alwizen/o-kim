<?php

namespace App\Filament\Widgets;

use App\Enums\ApplicationStatus;
use App\Enums\FindingStatus;
use App\Models\Finding;
use App\Models\InspectionItem;
use App\Models\KimApplication;
use App\Support\Widget\DepartmentScope;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Ringkasan pekerjaan harian petugas TAD (HSSE / Distribusi / QQ).
 *
 * Seluruh angka dibatasi pada fungsi yang boleh diinspeksi pengguna.
 */
class TadStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return DepartmentScope::isInspector();
    }

    protected function getStats(): array
    {
        $departments = DepartmentScope::inspectableValues();
        $userId = auth()->id();

        if ($departments === []) {
            return [];
        }

        // Butir wajib pada fungsi pengguna — dasar perhitungan checklist belum lengkap.
        $requiredItemIds = InspectionItem::query()
            ->whereHas('checklist', fn ($query) => $query
                ->whereIn('department', $departments)
                ->where('is_active', true))
            ->where('is_active', true)
            ->where('is_required', true)
            ->pluck('id');

        $readyToInspect = KimApplication::query()
            ->whereIn('status', [
                ApplicationStatus::Submitted->value,
                ApplicationStatus::Inspection->value,
                ApplicationStatus::RevisionRequired->value,
            ])
            ->whereNotNull('inspection_scheduled_at')
            ->where('inspection_scheduled_at', '<=', now())
            ->when($requiredItemIds->isNotEmpty(), fn ($query) => $query->whereHas(
                'inspectionResults',
                fn ($q) => $q->whereIn('inspection_item_id', $requiredItemIds),
                '<',
                $requiredItemIds->count(),
            ))
            ->count();

        $awaitingVerification = Finding::query()
            ->whereIn('department', $departments)
            ->where('status', FindingStatus::SubmittedForVerification->value)
            ->count();

        $openFindings = Finding::query()
            ->whereIn('department', $departments)
            ->whereIn('status', [
                FindingStatus::Open->value,
                FindingStatus::Rejected->value,
            ])
            ->count();

        $myFindings = Finding::query()
            ->whereIn('department', $departments)
            ->where('created_by', $userId)
            ->count();

        return [
            Stat::make('Siap Diperiksa', $readyToInspect)
                ->description('Jadwal tiba, checklist belum lengkap')
                ->descriptionIcon('heroicon-m-clipboard-document-check')
                ->color($readyToInspect > 0 ? 'warning' : 'gray')
                ->icon('heroicon-o-clipboard-document-check')
                ->url('/admin/kim-applications'),

            Stat::make('Menunggu Verifikasi Saya', $awaitingVerification)
                ->description('Tindak lanjut transportir menunggu keputusan')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color($awaitingVerification > 0 ? 'info' : 'gray')
                ->icon('heroicon-o-check-badge')
                ->url('/admin/findings'),

            Stat::make('Temuan Terbuka', $openFindings)
                ->description('Belum ditindaklanjuti transportir')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($openFindings > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-exclamation-triangle')
                ->url('/admin/findings'),

            Stat::make('Temuan Saya', $myFindings)
                ->description('Total temuan yang Anda catat')
                ->descriptionIcon('heroicon-m-user')
                ->color('gray')
                ->icon('heroicon-o-user')
                ->url('/admin/findings'),
        ];
    }
}
