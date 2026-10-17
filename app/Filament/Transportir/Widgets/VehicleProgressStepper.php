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

    protected string $view = 'filament.transportir.widgets.vehicle-progress-stepper';

    public ?int $applicationId = null;

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('transportir') ?? false;
    }

    public function mount(): void
    {
        $this->applicationId = $this->defaultApplicationId();
    }

    /**
     * Pengajuan milik perusahaan pengguna, terbaru lebih dahulu.
     *
     * @return Collection<int, KimApplication>
     */
    public function getApplications(): Collection
    {
        $transporterIds = auth()->user()->transporters()->pluck('transporters.id')->all();

        if ($transporterIds === []) {
            return new Collection;
        }

        return KimApplication::query()
            ->with('vehicle')
            ->whereIn('transporter_id', $transporterIds)
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
            $steps = $service->stepsFor($application);
            $currentIndex = $service->currentStepIndex($steps);
        }

        return [
            'options' => $this->getApplicationOptions(),
            'steps' => $steps,
            'currentIndex' => $currentIndex,
            'selectedApplication' => $application,
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
