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
    /**
     * Panel yang sedang merender stepper: 'transportir' atau 'admin'.
     * Dipakai untuk menyusun tautan tiap langkah agar mengarah ke panel yang tepat.
     */
    private string $panel = 'transportir';

    public function __construct(
        private KimApplicationService $applicationService,
        private InspectionService $inspectionService,
    ) {}

    /**
     * Susun langkah progres pengajuan untuk satu pengajuan KIM.
     *
     * Urutan: kendaraan, verifikasi dokumen, pemeriksaan & temuan, lalu
     * approval per fungsi (HSSE, Distribusi, QQ, RSD, FTM).
     * Langkah "KIM Terbit" tidak ditampilkan karena kartu sudah punya
     * halaman sendiri.
     *
     * `$panel` menentukan panel tujuan tautan tiap langkah (transportir/admin).
     *
     * State yang dipakai: done, active, warning, failed, pending.
     *
     * @return array<int, array{key: string, label: string, state: string, description: string, url: string}>
     */
    public function stepsFor(KimApplication $application, string $panel = 'transportir'): array
    {
        $this->panel = in_array($panel, ['transportir', 'admin'], true) ? $panel : 'transportir';

        $application->loadMissing(['vehicle', 'findings', 'kimCard', 'approvals']);

        return [
            $this->vehicleStep($application),
            $this->documentStep($application),
            $this->inspectionStep($application),
            ...$this->departmentApprovalSteps($application),
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
            'url' => $this->vehicleUrl($application->vehicle_id),
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
            'url' => $this->vehicleUrl($vehicle->id),
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
                'url' => $this->findingsUrl(),
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
                'url' => $this->findingsUrl(),
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
                'url' => $this->applicationUrl($application),
            ];
        }

        if ($isInspectionPhase) {
            return [
                'key' => 'inspection',
                'label' => 'Pemeriksaan & Temuan',
                'state' => 'active',
                'description' => $completedDepartments.' dari '.$totalDepartments.' fungsi selesai diperiksa',
                'url' => $this->applicationUrl($application),
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
            'url' => $this->applicationUrl($application),
        ];
    }

    /**
     * Tahap persetujuan, dipecah per pengawas: HSSE, Distribusi, QQ, RSD, lalu FTM.
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
        $rsd = Department::Rsd;
        $ftm = Department::Ftm;

        $approvedFunctions = collect($functions)
            ->filter(fn (Department $department) => $approvedDepartments->contains($department->value))
            ->count();

        $allFunctionsApproved = $approvedFunctions >= count($functions);
        $rsdApproved = $approvedDepartments->contains($rsd->value);
        $ftmApproved = $approvedDepartments->contains($ftm->value);
        $applicationRejected = $application->status === ApplicationStatus::Rejected;

        // KIM sementara: temuan sudah didispensasi FTM, sehingga approval pengawas
        // dan RSD tidak diperlukan dan hanya FTM yang menunggu.
        $isDispensation = $application->hasActiveDispensation();

        // Approval hanya boleh tampak berjalan setelah pemeriksaan tuntas:
        // seluruh checklist terisi dan tidak ada temuan penghalang.
        // KIM sementara dikecualikan karena temuan sudah didispensasi FTM.
        $completion = $this->departmentCompletionMap($application);

        $approvalOpen = $isDispensation
            || ($completion !== []
                && ! in_array(false, $completion, true)
                && ! $application->hasBlockingFindings());

        // Approver tercatat per fungsi, dipakai untuk menampilkan nama pengawas.
        $approverByDepartment = $approvals
            ->where('decision', ApprovalDecision::Approved)
            ->keyBy(fn ($approval) => $approval->department->value);

        $steps = [];
        $runningMarked = false;

        foreach ([...$functions, $rsd, $ftm] as $department) {
            $isFtm = $department === $ftm;
            $isRsd = $department === $rsd;
            $approved = $approvedDepartments->contains($department->value);
            $rejectedHere = $rejected !== null && $rejected->department === $department;

            [$state, $description] = $this->approvalStateFor(
                approved: $approved,
                rejectedHere: $rejectedHere,
                applicationRejected: $applicationRejected,
                isFtm: $isFtm,
                isRsd: $isRsd,
                allFunctionsApproved: $allFunctionsApproved,
                rsdApproved: $rsdApproved,
                ftmApproved: $ftmApproved,
                approvedBy: $approverByDepartment->get($department->value)?->approver?->name,
                isDispensation: $isDispensation,
                approvalOpen: $approvalOpen,
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
                'url' => $this->applicationUrl($application),
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
        bool $isRsd,
        bool $allFunctionsApproved,
        bool $rsdApproved,
        bool $ftmApproved,
        ?string $approvedBy,
        bool $isDispensation = false,
        bool $approvalOpen = false,
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
            if ($isDispensation || ($allFunctionsApproved && $rsdApproved)) {
                return ['active', 'Menunggu persetujuan FTM'];
            }

            if ($allFunctionsApproved) {
                return ['pending', 'Menunggu persetujuan RSD'];
            }

            return ['pending', 'Menunggu persetujuan fungsi'];
        }

        // RSD adalah gerbang sebelum FTM: menunggu ketiga fungsi selesai.
        if ($isRsd) {
            if ($isDispensation) {
                return ['pending', 'Tidak diperlukan — dispensasi FTM'];
            }

            if ($ftmApproved || ($allFunctionsApproved && $rsdApproved)) {
                return ['done', 'Disetujui'];
            }

            if ($allFunctionsApproved) {
                return ['active', 'Menunggu persetujuan RSD'];
            }

            if (! $approvalOpen) {
                return ['pending', 'Menunggu pemeriksaan selesai'];
            }

            return ['pending', 'Menunggu persetujuan fungsi'];
        }

        // Pada KIM sementara fungsi pengawas tidak perlu menyetujui.
        if ($isDispensation) {
            return ['pending', 'Tidak diperlukan — dispensasi FTM'];
        }

        // Pemeriksaan belum tuntas: approval fungsi belum berjalan.
        if (! $approvalOpen) {
            return ['pending', 'Menunggu pemeriksaan selesai'];
        }

        if ($ftmApproved || $allFunctionsApproved) {
            // Fungsi ini terlewat tetapi pengajuan sudah lolos; jangan tampak menggantung.
            return ['done', 'Disetujui'];
        }

        return ['active', 'Menunggu persetujuan'];
    }

    /**
     * URL halaman kendaraan sesuai panel yang sedang dipakai.
     */
    private function vehicleUrl(int $vehicleId): string
    {
        return $this->panel === 'admin'
            ? '/admin/vehicles/'.$vehicleId
            : '/transportir/vehicles/'.$vehicleId.'/edit';
    }

    /**
     * URL daftar temuan sesuai panel yang sedang dipakai.
     */
    private function findingsUrl(): string
    {
        return $this->panel === 'admin'
            ? '/admin/findings'
            : '/transportir/findings';
    }

    /**
     * URL detail pengajuan sesuai panel yang sedang dipakai.
     */
    private function applicationUrl(KimApplication $application): string
    {
        return '/'.$this->panel.'/kim-applications/'.$application->getRouteKey();
    }

    /**
     * Panel yang dipakai untuk menyusun tautan langkah.
     */
    public function panel(): string
    {
        return $this->panel;
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
