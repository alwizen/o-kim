<?php

namespace App\Filament\Widgets;

use App\Enums\AccountStatus;
use App\Enums\ApplicationStatus;
use App\Enums\FindingStatus;
use App\Enums\KimCardStatus;
use App\Models\Finding;
use App\Models\KimApplication;
use App\Models\KimCard;
use App\Models\User;
use App\Services\Document\DocumentExpiryService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('access_admin_panel') ?? false;
    }

    protected function getStats(): array
    {
        $pendingRegistrations = User::role('transportir')
            ->where('account_status', AccountStatus::Pending)
            ->count();

        $inInspection = KimApplication::whereIn('status', [
            ApplicationStatus::Submitted->value,
            ApplicationStatus::Inspection->value,
            ApplicationStatus::RevisionRequired->value,
        ])->count();

        $openFindings = Finding::where('status', '!=', FindingStatus::Verified->value)->count();

        $pendingApproval = KimApplication::where('status', ApplicationStatus::PendingApproval->value)->count();

        $activeCards = KimCard::where('status', KimCardStatus::Active->value)
            ->whereDate('expires_at', '>=', now()->toDateString())
            ->count();

        $expiry = app(DocumentExpiryService::class);
        $expiringDocs = $expiry->expiringCount();
        $expiredDocs = $expiry->expiredCount();

        return [
            Stat::make('Pendaftaran Menunggu Verifikasi', $pendingRegistrations)
                ->description('Transportir menunggu persetujuan')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color($pendingRegistrations > 0 ? 'warning' : 'gray')
                ->icon('heroicon-o-user-plus')
                ->url('/admin/users'),

            Stat::make('Pengajuan Dalam Pemeriksaan', $inInspection)
                ->description('Termasuk yang perlu perbaikan')
                ->descriptionIcon('heroicon-m-clipboard-document-check')
                ->color('info')
                ->icon('heroicon-o-clipboard-document-check')
                ->url('/admin/kim-applications'),

            Stat::make('Temuan Terbuka', $openFindings)
                ->description('Belum selesai diverifikasi')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($openFindings > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-exclamation-triangle')
                ->url('/admin/findings'),

            Stat::make('Menunggu Approval', $pendingApproval)
                ->description('Siap diputuskan')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color($pendingApproval > 0 ? 'warning' : 'gray')
                ->icon('heroicon-o-check-badge')
                ->url('/admin/kim-applications'),

            Stat::make('KIM Aktif', $activeCards)
                ->description('Terbit dan masih berlaku')
                ->descriptionIcon('heroicon-m-identification')
                ->color('success')
                ->icon('heroicon-o-identification'),

            Stat::make('Dokumen Mendekati Kedaluwarsa', $expiringDocs)
                ->description($expiredDocs > 0
                    ? $expiredDocs.' dokumen sudah kedaluwarsa'
                    : 'Dalam '.DocumentExpiryService::WARNING_THRESHOLD_DAYS.' hari')
                ->descriptionIcon('heroicon-m-document-text')
                ->color($expiredDocs > 0 ? 'danger' : ($expiringDocs > 0 ? 'warning' : 'gray'))
                ->icon('heroicon-o-document-text'),
        ];
    }
}
