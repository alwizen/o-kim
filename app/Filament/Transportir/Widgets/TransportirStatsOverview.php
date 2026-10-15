<?php

namespace App\Filament\Transportir\Widgets;

use App\Enums\ApplicationStatus;
use App\Enums\FindingStatus;
use App\Enums\KimCardStatus;
use App\Models\Finding;
use App\Models\KimApplication;
use App\Models\KimCard;
use App\Models\Vehicle;
use App\Services\Document\DocumentExpiryService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TransportirStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('transportir') ?? false;
    }

    protected function getStats(): array
    {
        $transporterIds = auth()->user()->transporters()->pluck('transporters.id')->all();

        $vehicleCount = Vehicle::whereIn('transporter_id', $transporterIds)->count();

        $runningApplications = KimApplication::whereIn('transporter_id', $transporterIds)
            ->whereIn('status', [
                ApplicationStatus::Draft->value,
                ApplicationStatus::Submitted->value,
                ApplicationStatus::Inspection->value,
                ApplicationStatus::RevisionRequired->value,
                ApplicationStatus::PendingApproval->value,
            ])
            ->count();

        $findingsNeedingAction = Finding::whereHas(
            'application',
            fn ($query) => $query->whereIn('transporter_id', $transporterIds)
        )->whereIn('status', [
            FindingStatus::Open->value,
            FindingStatus::Rejected->value,
        ])->count();

        $activeCards = KimCard::whereHas(
            'application',
            fn ($query) => $query->whereIn('transporter_id', $transporterIds)
        )->where('status', KimCardStatus::Active->value)
            ->whereDate('expires_at', '>=', now()->toDateString())
            ->count();

        $expiringCards = KimCard::whereHas(
            'application',
            fn ($query) => $query->whereIn('transporter_id', $transporterIds)
        )->where('status', KimCardStatus::Active->value)
            ->whereDate('expires_at', '>=', now()->toDateString())
            ->whereDate('expires_at', '<=', now()->addDays(30)->toDateString())
            ->count();

        $expiry = app(DocumentExpiryService::class);
        $expiringDocs = $expiry->expiringCount($transporterIds);
        $expiredDocs = $expiry->expiredCount($transporterIds);

        return [
            Stat::make('Total Kendaraan', $vehicleCount)
                ->description('Terdaftar di perusahaan Anda')
                ->descriptionIcon('heroicon-m-truck')
                ->color('info')
                ->icon('heroicon-o-truck')
                ->url('/transportir/vehicles'),

            Stat::make('Pengajuan Berjalan', $runningApplications)
                ->description('Belum selesai diproses')
                ->descriptionIcon('heroicon-m-document-plus')
                ->color($runningApplications > 0 ? 'warning' : 'gray')
                ->icon('heroicon-o-document-plus')
                ->url('/transportir/kim-applications'),

            Stat::make('Temuan Perlu Tindak Lanjut', $findingsNeedingAction)
                ->description('Perlu Anda perbaiki')
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color($findingsNeedingAction > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-wrench-screwdriver')
                ->url('/transportir/findings'),

            Stat::make('KIM Aktif', $activeCards)
                ->description('Masih berlaku')
                ->descriptionIcon('heroicon-m-identification')
                ->color('success')
                ->icon('heroicon-o-identification')
                ->url('/transportir/kim-applications'),

            Stat::make('KIM Mendekati Kedaluwarsa', $expiringCards)
                ->description('Berlaku ≤ 30 hari lagi')
                ->descriptionIcon('heroicon-m-clock')
                ->color($expiringCards > 0 ? 'warning' : 'gray')
                ->icon('heroicon-o-clock'),

            Stat::make('Dokumen Perlu Diperbarui', $expiringDocs)
                ->description($expiredDocs > 0
                    ? $expiredDocs.' dokumen sudah kedaluwarsa'
                    : 'Dalam '.DocumentExpiryService::WARNING_THRESHOLD_DAYS.' hari')
                ->descriptionIcon('heroicon-m-document-text')
                ->color($expiredDocs > 0 ? 'danger' : ($expiringDocs > 0 ? 'warning' : 'gray'))
                ->icon('heroicon-o-document-text'),
        ];
    }
}
