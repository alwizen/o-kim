<?php

namespace App\Filament\Widgets;

use App\Enums\ApplicationStatus;
use App\Enums\ApprovalDecision;
use App\Enums\FindingStatus;
use App\Models\Finding;
use App\Models\KimApplication;
use App\Support\Widget\DepartmentScope;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Ringkasan pekerjaan Pengawas fungsi (HSSE / Distribusi / QQ).
 */
class PengawasStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return DepartmentScope::isApprover();
    }

    protected function getStats(): array
    {
        $departments = DepartmentScope::approvableValues();

        if ($departments === []) {
            return [];
        }

        $waitingApproval = KimApplication::query()
            ->whereIn('status', [
                ApplicationStatus::PendingApproval->value,
                ApplicationStatus::Inspection->value,
                ApplicationStatus::RevisionRequired->value,
            ])
            ->whereDoesntHave('approvals', fn ($query) => $query
                ->whereIn('department', $departments)
                ->where('decision', ApprovalDecision::Approved->value))
            ->count();

        $findingsToVerify = Finding::query()
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

        $activeDispensations = Finding::query()
            ->whereIn('department', $departments)
            ->where('status', FindingStatus::Dispensation->value)
            ->count();

        return [
            Stat::make('Menunggu Approval Saya', $waitingApproval)
                ->description('Pengajuan siap diputuskan fungsi Anda')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color($waitingApproval > 0 ? 'warning' : 'gray')
                ->icon('heroicon-o-check-badge')
                ->url('/admin/kim-applications'),

            Stat::make('Temuan Menunggu Verifikasi', $findingsToVerify)
                ->description('Tindak lanjut transportir')
                ->descriptionIcon('heroicon-m-clipboard-document-check')
                ->color($findingsToVerify > 0 ? 'info' : 'gray')
                ->icon('heroicon-o-clipboard-document-check')
                ->url('/admin/findings'),

            Stat::make('Temuan Terbuka', $openFindings)
                ->description('Belum ditindaklanjuti')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($openFindings > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-exclamation-triangle')
                ->url('/admin/findings'),

            Stat::make('Dispensasi Aktif', $activeDispensations)
                ->description('Temuan dalam penundaan perbaikan')
                ->descriptionIcon('heroicon-m-clock')
                ->color($activeDispensations > 0 ? 'warning' : 'gray')
                ->icon('heroicon-o-clock')
                ->url('/admin/findings'),
        ];
    }
}
