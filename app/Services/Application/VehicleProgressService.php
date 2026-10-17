<?php

namespace App\Services\Application;

use App\Enums\ApplicationStatus;
use App\Enums\Department;
use App\Enums\FindingStatus;
use App\Models\KimApplication;
use App\Models\Vehicle;
use App\Services\Inspection\InspectionService;

class VehicleProgressService
{
    public function __construct(
        private KimApplicationService $applicationService,
        private InspectionService $inspectionService,
    ) {}

    /**
     * Susun lima langkah progres pengajuan untuk satu pengajuan KIM.
     *
     * State yang dipakai: done, active, warning, failed, pending.
     *
     * @return array<int, array{key: string, label: string, state: string, description: string, url: string}>
     */
    public function stepsFor(KimApplication $application): array
    {
        $application->loadMissing(['vehicle', 'findings', 'kimCard']);

        return [
            $this->vehicleStep($application),
            $this->documentStep($application),
            $this->checklistStep($application),
            $this->findingStep($application),
            $this->kimStep($application),
        ];
    }

    /**
     * Langkah aktif pertama (untuk ditandai sebagai posisi saat ini).
     */
    public function currentStepIndex(array $steps): int
    {
        foreach ($steps as $index => $step) {
            if (in_array($step['state'], ['active', 'warning', 'failed'], true)) {
                return $index;
            }
        }

        return count($steps) - 1;
    }

    /**
     * @return array{key: string, label: string, state: string, description: string, url: string}
     */
    private function vehicleStep(KimApplication $application): array
    {
        return [
            'key' => 'vehicle',
            'label' => 'Pendaftaran Kendaraan',
            'state' => 'done',
            'description' => $application->vehicle->registration_number,
            'url' => '/transportir/vehicles/'.$application->vehicle_id.'/edit',
        ];
    }

    /**
     * @return array{key: string, label: string, state: string, description: string, url: string}
     */
    private function documentStep(KimApplication $application): array
    {
        $vehicle = $application->vehicle;
        $missing = $this->applicationService->missingRequiredDocumentCodes($vehicle);

        $state = 'pending';
        $description = 'Belum ada dokumen wajib diunggah';

        if ($missing->isEmpty()) {
            $state = 'done';
            $description = 'Seluruh dokumen wajib terverifikasi';
        } elseif ($this->hasUploadedRequiredDocuments($vehicle)) {
            $state = 'active';
            $description = 'Menunggu verifikasi: '.$missing->implode(', ');
        }

        return [
            'key' => 'documents',
            'label' => 'Verifikasi Dokumen',
            'state' => $state,
            'description' => $description,
            'url' => '/transportir/vehicles/'.$vehicle->id.'/edit',
        ];
    }

    private function hasUploadedRequiredDocuments(Vehicle $vehicle): bool
    {
        return $vehicle->documents()
            ->whereNull('replaced_by_id')
            ->exists();
    }

    /**
     * @return array{key: string, label: string, state: string, description: string, url: string}
     */
    private function checklistStep(KimApplication $application): array
    {
        $completedDepartments = collect(Department::inspectingDepartments())
            ->filter(fn (Department $department) => $this->inspectionService
                ->isDepartmentInspectionComplete($application, $department))
            ->count();

        $totalDepartments = count(Department::inspectingDepartments());

        $state = 'pending';
        $description = 'Menunggu pengajuan dikirim';

        if ($completedDepartments >= $totalDepartments) {
            $state = 'done';
            $description = 'Seluruh checklist fungsi selesai';
        } elseif (in_array($application->status, [
            ApplicationStatus::Submitted,
            ApplicationStatus::Inspection,
            ApplicationStatus::RevisionRequired,
        ], true)) {
            $state = 'active';
            $description = $completedDepartments.' dari '.$totalDepartments.' fungsi selesai diperiksa';
        }

        return [
            'key' => 'checklist',
            'label' => 'Checklist Pemeriksaan',
            'state' => $state,
            'description' => $description,
            'url' => '/transportir/kim-applications/'.$application->getRouteKey(),
        ];
    }

    /**
     * @return array{key: string, label: string, state: string, description: string, url: string}
     */
    private function findingStep(KimApplication $application): array
    {
        $findings = $application->findings;
        $openFindings = $findings->where('status', '!=', FindingStatus::Verified);

        if ($application->status === ApplicationStatus::Rejected) {
            $state = 'failed';
            $description = 'Pengajuan ditolak';
        } elseif ($findings->isEmpty()) {
            $state = 'done';
            $description = 'Tidak ada temuan';
        } elseif ($openFindings->isEmpty()) {
            $state = 'done';
            $description = 'Seluruh temuan selesai';
        } else {
            $state = 'warning';
            $description = $openFindings->count().' temuan perlu ditindaklanjuti';
        }

        return [
            'key' => 'findings',
            'label' => 'Temuan Pemeriksaan',
            'state' => $state,
            'description' => $description,
            'url' => '/transportir/findings',
        ];
    }

    /**
     * @return array{key: string, label: string, state: string, description: string, url: string}
     */
    private function kimStep(KimApplication $application): array
    {
        $card = $application->kimCard;

        if ($card !== null) {
            $state = 'done';
            $description = $card->kim_number;
        } elseif ($application->status === ApplicationStatus::Approved) {
            $state = 'active';
            $description = 'Menunggu penerbitan KIM';
        } elseif ($application->status === ApplicationStatus::Rejected) {
            $state = 'failed';
            $description = 'Tidak diterbitkan';
        } else {
            $state = 'pending';
            $description = 'Menunggu persetujuan';
        }

        return [
            'key' => 'kim',
            'label' => 'KIM Terbit',
            'state' => $state,
            'description' => $description,
            'url' => '/transportir/kim-applications/'.$application->getRouteKey(),
        ];
    }
}
