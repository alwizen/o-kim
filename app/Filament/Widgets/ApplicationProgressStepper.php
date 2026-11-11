<?php

namespace App\Filament\Widgets;

use App\Enums\ApplicationStatus;
use App\Models\KimApplication;
use App\Services\Application\VehicleProgressService;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

/**
 * Stepper progres pengajuan KIM pada panel admin.
 *
 * Menampilkan tahapan satu pengajuan lintas transportir. Hanya pengguna dengan
 * permission `view_progress_stepper` yang dapat melihat widget ini.
 */
class ApplicationProgressStepper extends Widget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    /**
     * Render langsung tanpa skeleton agar stepper terlihat segera di dashboard.
     */
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.progress-stepper';

    public ?int $applicationId = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('view_progress_stepper') ?? false;
    }

    public function mount(): void
    {
        $this->applicationId = $this->defaultApplicationId();
    }

    private ?Collection $applicationsCache = null;

    /**
     * Pengajuan yang masih berjalan (KIM belum terbit / belum ditolak).
     *
     * Pengajuan yang KIM-nya sudah terbit tidak ditampilkan karena progresnya
     * sudah selesai dan tidak perlu dipantau lagi.
     *
     * @return Collection<int, KimApplication>
     */
    public function getApplications(): Collection
    {
        if ($this->applicationsCache !== null) {
            return $this->applicationsCache;
        }

        return $this->applicationsCache = KimApplication::query()
            ->with(['vehicle', 'transporter'])
            ->whereNotIn('status', [
                ApplicationStatus::Issued->value,
                ApplicationStatus::Cancelled->value,
            ])
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    public function getApplicationOptions(): array
    {
        return $this->getApplications()
            ->mapWithKeys(fn (KimApplication $application) => [
                $application->id => ($application->vehicle?->registration_number ?? '—')
                    .' — '.$application->application_number
                    .' ('.$application->transporter?->company_name.')',
            ])
            ->all();
    }

    protected function getViewData(): array
    {
        $application = $this->getSelectedApplication();
        $steps = [];
        $currentIndex = 0;

        if ($application !== null) {
            $service = app(VehicleProgressService::class);
            $steps = $service->stepsFor($application, 'admin');
            $currentIndex = $service->currentStepIndex($steps);
        }

        return [
            'options' => $this->getApplicationOptions(),
            'steps' => $steps,
            'currentIndex' => $currentIndex,
            'selectedApplication' => $application,
            'heading' => 'Progres Pengajuan KIM',
            'description' => 'Pantau tahapan pengajuan lintas transportir hingga persetujuan akhir.',
            'emptyTitle' => 'Belum ada pengajuan berjalan',
            'emptyText' => 'Semua pengajuan sudah terbit atau dibatalkan.',
            'createUrl' => null,
        ];
    }

    public function getSelectedApplication(): ?KimApplication
    {
        if ($this->applicationId === null) {
            return null;
        }

        return $this->getApplications()->firstWhere('id', $this->applicationId);
    }

    private function defaultApplicationId(): ?int
    {
        $applications = $this->getApplications();

        if ($applications->isEmpty()) {
            return null;
        }

        $openStatuses = collect(ApplicationStatus::cases())
            ->filter(fn (ApplicationStatus $status) => $status->isOpen())
            ->map->value
            ->all();

        $open = $applications->first(
            fn (KimApplication $application) => in_array($application->status->value, $openStatuses, true)
        );

        return ($open ?? $applications->first())->id;
    }
}
