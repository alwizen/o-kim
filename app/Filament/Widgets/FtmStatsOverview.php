<?php

namespace App\Filament\Widgets;

use App\Enums\ApplicationStatus;
use App\Enums\ApprovalDecision;
use App\Enums\Department;
use App\Enums\DispensationRequestStatus;
use App\Enums\FindingStatus;
use App\Enums\InternalRole;
use App\Enums\KimCardStatus;
use App\Models\Finding;
use App\Models\FindingDispensationRequest;
use App\Models\KimApplication;
use App\Models\KimCard;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Ringkasan keputusan yang menunggu Fuel Terminal Manager.
 */
class FtmStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasRole(InternalRole::Ftm->value) ?? false;
    }

    protected function getStats(): array
    {
        $pendingRequests = FindingDispensationRequest::query()
            ->where('status', DispensationRequestStatus::Pending->value)
            ->count();

        $pendingFinalApproval = KimApplication::query()
            ->where('status', ApplicationStatus::PendingApproval->value)
            ->whereHas('approvals', fn ($query) => $query
                ->where('department', Department::Rsd->value)
                ->where('decision', ApprovalDecision::Approved->value))
            ->count();

        $activeDispensations = Finding::query()
            ->where('status', FindingStatus::Dispensation->value)
            ->count();

        $expiringSoon = Finding::query()
            ->where('status', FindingStatus::Dispensation->value)
            ->whereNotNull('dispensation_due_date')
            ->whereDate('dispensation_due_date', '>=', now()->toDateString())
            ->whereDate('dispensation_due_date', '<=', now()->addDays(14)->toDateString())
            ->count();

        $dispensationCards = KimCard::query()
            ->where('is_dispensation', true)
            ->where('status', KimCardStatus::Active->value)
            ->whereDate('expires_at', '>=', now()->toDateString())
            ->count();

        return [
            Stat::make('Permohonan KIM Sementara', $pendingRequests)
                ->description('Menunggu keputusan Anda')
                ->descriptionIcon('heroicon-m-inbox-arrow-down')
                ->color($pendingRequests > 0 ? 'warning' : 'gray')
                ->icon('heroicon-o-inbox-arrow-down')
                ->url('/admin/dispensation-requests'),

            Stat::make('Menunggu Approval Akhir', $pendingFinalApproval)
                ->description('Fungsi & RSD sudah setuju')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color($pendingFinalApproval > 0 ? 'info' : 'gray')
                ->icon('heroicon-o-check-badge')
                ->url('/admin/kim-applications'),

            Stat::make('Dispensasi Berjalan', $activeDispensations)
                ->description($expiringSoon > 0
                    ? $expiringSoon.' akan berakhir ≤ 14 hari'
                    : 'Tidak ada yang mendesak')
                ->descriptionIcon('heroicon-m-clock')
                ->color($expiringSoon > 0 ? 'danger' : 'warning')
                ->icon('heroicon-o-clock')
                ->url('/admin/findings'),

            Stat::make('KIM Sementara Aktif', $dispensationCards)
                ->description('Berlaku dengan batas dispensasi')
                ->descriptionIcon('heroicon-m-identification')
                ->color('success')
                ->icon('heroicon-o-identification'),
        ];
    }
}
