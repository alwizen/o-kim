<?php

namespace App\Filament\Transportir\Widgets;

use App\Enums\ApplicationStatus;
use App\Models\KimApplication;
use App\Services\Application\VehicleProgressService;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

class VehicleProgressStepper extends Widget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.progress-stepper';

    public ?int $applicationId = null;

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('transportir') ?? false;
    }

    public function mount(): void
    {
        $this->applicationId = $this->defaultApplicationId();
    }

    private ?Collection $applicationsCache = null;

    /**
     * Pengajuan milik perusahaan pengguna, terbaru lebih dahulu.
     *
     * Pengajuan yang KIM-nya sudah terbit atau dibatalkan tidak ditampilkan:
     * progresnya sudah selesai sehingga tidak perlu dipantau lagi.
     * Hasil di-cache per request agar tidak query berulang saat widget dirender.
     *
     * @return Collection<int, KimApplication>
     */
    public function getApplications(): Collection
    {
        if ($this->applicationsCache !== null) {
            return $this->applicationsCache;
        }

        $transporterIds = auth()->user()->transporters()->pluck('transporters.id')->all();

        if ($transporterIds === []) {
            return $this->applicationsCache = new Collection;
        }

        return $this->applicationsCache = KimApplication::query()
            ->with('vehicle')
            ->whereIn('transporter_id', $transporterIds)
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
                $application->id => $application->vehicle->registration_number.' — '.$application->application_number,
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
            $steps = $service->stepsFor($application, 'transportir');
            $currentIndex = $service->currentStepIndex($steps);
        }

        return [
            'options' => $this->getApplicationOptions(),
            'steps' => $steps,
            'currentIndex' => $currentIndex,
            'selectedApplication' => $application,
            'heading' => 'Progres Pengajuan KIM',
            'description' => 'Tahapan pengajuan mulai dari kendaraan terdaftar hingga persetujuan akhir.',
            'emptyTitle' => 'Belum ada pengajuan KIM',
            'emptyText' => 'Buat pengajuan KIM terlebih dahulu agar progres tiap tahap dapat dipantau di sini.',
            'createUrl' => '/transportir/kim-applications/create',
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
