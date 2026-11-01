<?php

namespace App\Services\Application;

use App\Enums\ApplicationStatus;
use App\Enums\ApprovalDecision;
use App\Enums\Department;
use App\Models\Finding;
use App\Models\InspectionItem;
use App\Models\KimApplication;
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
     * Checklist dan temuan digabung menjadi satu tahap "Pemeriksaan & Temuan"
     * karena keduanya merupakan satu rangkaian pemeriksaan yang sama.
     *
     * State yang dipakai: done, active, warning, failed, pending.
     *
     * @return array<int, array{key: string, label: string, state: string, description: string, url: string}>
     */
    public function stepsFor(KimApplication $application): array
    {
        $application->loadMissing(['vehicle', 'findings', 'kimCard', 'approvals']);

        return [
            $this->vehicleStep($application),
            $this->documentStep($application),
            $this->inspectionStep($application),
            ...$this->departmentApprovalSteps($application),
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
        $unverified = $this->applicationService->unverifiedDocumentCodes($vehicle);

        $state = 'pending';
        $description = 'Belum ada dokumen wajib diunggah';

        if ($unverified->isEmpty()) {
            $state = 'done';
            $description = 'Seluruh dokumen wajib terverifikasi';
        } elseif ($missing->isEmpty()) {
            $state = 'active';
            $description = 'Menunggu verifikasi: '.$unverified->implode(', ');
        }

        return [
            'key' => 'documents',
            'label' => 'Verifikasi Dokumen',
            'state' => $state,
            'description' => $description,
            'url' => '/transportir/vehicles/'.$vehicle->id.'/edit',
        ];
    }

    /**
     * Tahap pemeriksaan: progres checklist seluruh fungsi sekaligus status temuan.
     *
     * @return array{key: string, label: string, state: string, description: string, url: string}
     */
    private function inspectionStep(KimApplication $application): array
    {
        $completion = $this->departmentCompletionMap($application);
        $completedDepartments = count(array_filter($completion));
        $totalDepartments = count(Department::inspectingDepartments());

        $findings = $application->findings;
        $openFindings = $findings->filter(
            fn (Finding $finding) => $finding->blocksApproval()
        );

        $checklistDone = $completedDepartments >= $totalDepartments;
        $findingsDone = $openFindings->isEmpty();

        /*
         * Pemeriksaan baru boleh tampil berjalan setelah petugas menetapkan
         * jadwal DAN tanggalnya tiba. Pengajuan yang baru dikirim (submitted)
         * masih menunggu penjadwalan, jadi tidak boleh terlihat sedang diperiksa.
         *
         * Pemeriksaan yang sudah ada progres/checklist juga dianggap sudah mulai
         * agar tidak mundur ke pending saat status belum berubah.
         */
        $inspectionStarted = $application->isInspectionDue()
            || $completedDepartments > 0;

        $isInspectionPhase = $inspectionStarted
            && in_array($application->status, [
                ApplicationStatus::Submitted,
                ApplicationStatus::Inspection,
                ApplicationStatus::RevisionRequired,
            ], true);

        if ($application->status === ApplicationStatus::Rejected) {
            return [
                'key' => 'inspection',
                'label' => 'Pemeriksaan & Temuan',
                'state' => 'failed',
                'description' => 'Pengajuan ditolak',
                'url' => '/transportir/findings',
            ];
        }

        // Masih ada temuan terbuka: ini yang paling perlu perhatian transportir.
        if (! $findingsDone) {
            return [
                'key' => 'inspection',
                'label' => 'Pemeriksaan & Temuan',
                'state' => 'warning',
                'description' => $openFindings->count().' temuan perlu ditindaklanjuti'
                    .($checklistDone ? '' : ' · checklist '.$completedDepartments.'/'.$totalDepartments.' fungsi'),
                'url' => '/transportir/findings',
            ];
        }

        if ($checklistDone && $findingsDone) {
            return [
                'key' => 'inspection',
                'label' => 'Pemeriksaan & Temuan',
                'state' => 'done',
                'description' => $findings->isEmpty()
                    ? 'Seluruh checklist selesai, tanpa temuan'
                    : 'Seluruh checklist selesai dan temuan tuntas',
                'url' => '/transportir/kim-applications/'.$application->getRouteKey(),
            ];
        }

        if ($isInspectionPhase) {
            return [
                'key' => 'inspection',
                'label' => 'Pemeriksaan & Temuan',
                'state' => 'active',
                'description' => $completedDepartments.' dari '.$totalDepartments.' fungsi selesai diperiksa',
                'url' => '/transportir/kim-applications/'.$application->getRouteKey(),
            ];
        }

        // Belum masuk fase pemeriksaan: jelaskan apa yang sedang ditunggu.
        $waitingForSchedule = in_array($application->status, [
            ApplicationStatus::Submitted,
            ApplicationStatus::Inspection,
            ApplicationStatus::RevisionRequired,
        ], true);

        if ($waitingForSchedule && $application->isInspectionScheduled()) {
            $description = 'Pemeriksaan dijadwalkan pada '
                .$application->inspection_scheduled_at->translatedFormat('d M Y H:i');
        } elseif ($waitingForSchedule) {
            $description = 'Menunggu penjadwalan pemeriksaan';
        } else {
            $description = 'Menunggu pengajuan dikirim';
        }

        return [
            'key' => 'inspection',
            'label' => 'Pemeriksaan & Temuan',
            'state' => 'pending',
            'description' => $description,
            'url' => '/transportir/kim-applications/'.$application->getRouteKey(),
        ];
    }

    /**
     * Tahap persetujuan, dipecah per pengawas: HSSE, Distribusi, QQ, lalu FTM.
     *
     * Setiap fungsi punya langkahnya sendiri agar transportir dapat melihat
     * fungsi mana yang sudah menyetujui dan mana yang masih menunggu.
     *
     * @return array<int, array{key: string, label: string, state: string, description: string, url: string}>
     */
    private function departmentApprovalSteps(KimApplication $application): array
    {
        $approvals = $application->approvals;

        $approvedDepartments = $approvals
            ->where('decision', ApprovalDecision::Approved)
            ->pluck('department')
            ->map(fn (Department $department) => $department->value)
            ->unique();

        $rejected = $approvals->first(
            fn ($approval) => $approval->decision === ApprovalDecision::Rejected
        );

        $functions = Department::inspectingDepartments();
        $ftm = Department::Ftm;

        $approvedFunctions = collect($functions)
            ->filter(fn (Department $department) => $approvedDepartments->contains($department->value))
            ->count();

        $allFunctionsApproved = $approvedFunctions >= count($functions);
        $ftmApproved = $approvedDepartments->contains($ftm->value);
        $applicationRejected = $application->status === ApplicationStatus::Rejected;

        // Approver tercatat per fungsi, dipakai untuk menampilkan nama pengawas.
        $approverByDepartment = $approvals
            ->where('decision', ApprovalDecision::Approved)
            ->keyBy(fn ($approval) => $approval->department->value);

        $steps = [];
        $runningMarked = false;

        foreach ([...$functions, $ftm] as $department) {
            $isFtm = $department === $ftm;
            $approved = $approvedDepartments->contains($department->value);
            $rejectedHere = $rejected !== null && $rejected->department === $department;

            [$state, $description] = $this->approvalStateFor(
                approved: $approved,
                rejectedHere: $rejectedHere,
                applicationRejected: $applicationRejected,
                isFtm: $isFtm,
                allFunctionsApproved: $allFunctionsApproved,
                ftmApproved: $ftmApproved,
                approvedBy: $approverByDepartment->get($department->value)?->approver?->name,
            );

            // Hanya satu fungsi yang ditandai berjalan agar transportir tahu
            // fungsi mana yang sedang ditunggu; sisanya tetap pending.
            if ($state === 'active') {
                if ($runningMarked) {
                    $state = 'pending';
                }

                $runningMarked = true;
            }

            $steps[] = [
                'key' => $department->value,
                'label' => $isFtm ? 'FTM' : 'Pengawas '.$department->label(),
                'state' => $state,
                'description' => $description,
                'url' => '/transportir/kim-applications/'.$application->getRouteKey(),
            ];
        }

        return $steps;
    }

    /**
     * Tentukan state dan keterangan satu langkah persetujuan fungsi.
     *
     * @return array{0: string, 1: string}
     */
    private function approvalStateFor(
        bool $approved,
        bool $rejectedHere,
        bool $applicationRejected,
        bool $isFtm,
        bool $allFunctionsApproved,
        bool $ftmApproved,
        ?string $approvedBy,
    ): array {
        $approvedText = fn (string $fallback): string => $approvedBy !== null
            ? 'Disetujui '.$approvedBy
            : $fallback;

        if ($rejectedHere) {
            return ['failed', 'Ditolak'];
        }

        // FTM tidak menyetujui bila pengajuan sudah ditolak fungsi lain.
        if ($applicationRejected && ! $approved) {
            return [$isFtm ? 'failed' : 'pending', 'Pengajuan ditolak'];
        }

        if ($approved) {
            return ['done', $approvedText('Disetujui')];
        }

        if ($isFtm) {
            if ($allFunctionsApproved) {
                return ['active', 'Menunggu persetujuan FTM'];
            }

            return ['pending', 'Menunggu persetujuan fungsi'];
        }

        if ($ftmApproved || $allFunctionsApproved) {
            // Fungsi ini terlewat tetapi pengajuan sudah lolos; jangan tampak menggantung.
            return ['done', 'Disetujui'];
        }

        return ['active', 'Menunggu persetujuan'];
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

    /**
     * Peta penyelesaian checklist per departemen dalam dua query agregat
     * (bukan dua query per departemen).
     *
     * @return array<string, bool> [department value => complete]
     */
    public function departmentCompletionMap(KimApplication $application): array
    {
        $requiredByDepartment = InspectionItem::query()
            ->where('is_active', true)
            ->where('is_required', true)
            ->whereHas('checklist', fn ($query) => $query->where('is_active', true))
            ->with('checklist:id,department')
            ->get()
            ->groupBy(fn (InspectionItem $item) => $item->checklist->department->value)
            ->map(fn ($items) => $items->pluck('id')->all());

        $completedItemIds = $application->inspectionResults()
            ->pluck('inspection_item_id')
            ->flip();

        $map = [];

        foreach (Department::inspectingDepartments() as $department) {
            $requiredIds = $requiredByDepartment->get($department->value, []);

            $map[$department->value] = $requiredIds === []
                || collect($requiredIds)->every(fn (int $id) => $completedItemIds->has($id));
        }

        return $map;
    }
}
