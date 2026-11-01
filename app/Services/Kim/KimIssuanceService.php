<?php

namespace App\Services\Kim;

use App\Enums\ApplicationStatus;
use App\Enums\ApprovalDecision;
use App\Enums\Department;
use App\Enums\FindingStatus;
use App\Enums\KimCardStatus;
use App\Models\KimApplication;
use App\Models\KimCard;
use App\Models\User;
use App\Notifications\KimCardIssued;
use App\Notifications\KimCardIssuedAsDispensationStaff;
use App\Services\Approval\ApprovalService;
use App\Services\Setting\SettingsService;
use App\Support\AppNotifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class KimIssuanceService
{
    public function __construct(
        private ApprovalService $approvalService,
        private KimValidityService $validityService,
    ) {}

    /**
     * Terbitkan KIM. Wajib dalam transaction + lock untuk mencegah penerbitan ganda.
     */
    public function issue(KimApplication $application): KimCard
    {
        return DB::transaction(function () use ($application) {
            $application = KimApplication::lockForUpdate()->findOrFail($application->id);

            $this->ensureIssuable($application);

            $issuedAt = now();
            $dispensationDueDate = $this->earliestDispensationDueDate($application);
            $expiresAt = $this->validityService->calculateExpiresAt($application, $issuedAt, $dispensationDueDate);

            $card = KimCard::create([
                'kim_application_id' => $application->id,
                'kim_number' => $this->generateKimNumber(),
                'issued_at' => $issuedAt,
                'expires_at' => $expiresAt,
                'qr_token' => Str::random(48),
                'status' => KimCardStatus::Active,
                'is_dispensation' => $dispensationDueDate !== null,
                'dispensation_expires_at' => $dispensationDueDate?->toDateString(),
            ]);

            $application->update([
                'status' => ApplicationStatus::Issued,
                'issued_at' => $issuedAt,
                'expires_at' => $expiresAt,
            ]);

            $registrationNumber = $application->vehicle->registration_number;
            $expiresAtText = $expiresAt->translatedFormat('d M Y');
            $issuedBy = auth()->user()?->name ?? 'Fuel Terminal Manager';
            $isDispensation = $dispensationDueDate !== null;
            $applicationNumber = $application->application_number;

            DB::afterCommit(fn () => AppNotifier::notifyTransporter(
                $application->transporter_id,
                new KimCardIssued(
                    $card->kim_number,
                    $registrationNumber,
                    $expiresAtText,
                ),
            ));

            // KIM sementara tidak melewati approval pengawas, jadi pengawas
            // diberi tahu bahwa NOPOL tersebut memperoleh dispensasi FTM.
            if ($isDispensation) {
                DB::afterCommit(fn () => AppNotifier::notifyRole(
                    ['pengawas_hsse', 'pengawas_distribusi', 'pengawas_qq'],
                    new KimCardIssuedAsDispensationStaff(
                        $applicationNumber,
                        $registrationNumber,
                        $card->kim_number,
                        $expiresAtText,
                        $issuedBy,
                    ),
                ));
            }

            return $card;
        });
    }

    /**
     * Terbitkan KIM sementara dalam satu langkah setelah dispensasi disetujui FTM.
     *
     * Alur normal mensyaratkan approval FTM final lebih dulu. Untuk KIM sementara,
     * approval pengawas tidak diperlukan dan FTM sendiri yang memutuskan dispensasi,
     * sehingga penerbitan dilakukan atas nama FTM pada aksi yang sama.
     *
     * Aman dipanggil berulang: bila approval FTM sudah ada, langkah approval dilewati.
     */
    public function issueProvisional(KimApplication $application, User $ftm): KimCard
    {
        $application = $application->fresh();

        $hasActiveDispensation = $application->hasActiveDispensation();

        if (! $hasActiveDispensation) {
            throw ValidationException::withMessages([
                'dispensation' => 'Pengajuan ini tidak memiliki dispensasi aktif.',
            ]);
        }

        $ftmApproved = $application->approvals()
            ->where('department', Department::Ftm->value)
            ->where('decision', ApprovalDecision::Approved)
            ->exists();

        if (! $ftmApproved) {
            // KIM sementara hanya butuh FTM; approval pengawas dilewati.
            $this->approvalService->decide(
                $application,
                Department::Ftm,
                ApprovalDecision::Approved,
                $ftm,
                'Dispensasi FTM disetujui; KIM sementara diterbitkan.',
            );
        }

        return $this->issue($application->fresh());
    }

    /**
     * Backend wajib memastikan seluruh prasyarat sebelum penerbitan (PRD 7.5).
     */
    public function ensureIssuable(KimApplication $application): void
    {
        if ($application->status !== ApplicationStatus::Approved) {
            throw ValidationException::withMessages([
                'status' => 'KIM hanya dapat diterbitkan dari pengajuan berstatus disetujui.',
            ]);
        }

        if ($application->kimCard) {
            throw ValidationException::withMessages([
                'kim' => 'KIM untuk pengajuan ini sudah diterbitkan.',
            ]);
        }

        if ($application->hasBlockingFindings()) {
            throw ValidationException::withMessages([
                'findings' => 'Masih ada temuan yang belum selesai atau dispensasinya telah kedaluwarsa.',
            ]);
        }

        // KIM sementara cukup approval FTM: temuan terkait sudah didispensasi
        // sehingga pengawas fungsi tidak perlu menyetujui lagi.
        $requiresFunctionalApprovals = ! $application->hasActiveDispensation();

        if ($requiresFunctionalApprovals) {
            foreach (Department::inspectingDepartments() as $department) {
                $has = $application->approvals()
                    ->where('department', $department->value)
                    ->where('decision', ApprovalDecision::Approved)
                    ->exists();

                if (! $has) {
                    throw ValidationException::withMessages([
                        'approvals' => 'Approval '.$department->label().' belum tersedia.',
                    ]);
                }
            }
        }

        $ftmApproved = $application->approvals()
            ->where('department', Department::Ftm->value)
            ->where('decision', ApprovalDecision::Approved)
            ->exists();

        if (! $ftmApproved) {
            throw ValidationException::withMessages([
                'approvals' => 'Approval final FTM belum tersedia.',
            ]);
        }
    }

    /**
     * Batas dispensasi terawal pada pengajuan (null bila tidak ada dispensasi).
     */
    private function earliestDispensationDueDate(KimApplication $application): ?Carbon
    {
        $dueDate = $application->findings()
            ->where('status', FindingStatus::Dispensation->value)
            ->whereNotNull('dispensation_due_date')
            ->min('dispensation_due_date');

        return $dueDate !== null ? Carbon::parse($dueDate) : null;
    }

    /**
     * Format nomor KIM disusun dari pengaturan sistem:
     * {prefix}/{periode}/{urutan} — contoh KIM/FT-TGL/2026/10/0001
     */
    private function generateKimNumber(): string
    {
        $settings = app(SettingsService::class);

        $segments = [trim($settings->kimNumberPrefix(), '/')];

        $periodFormat = $settings->kimNumberPeriodFormat();

        $period = $settings->formatPeriod($periodFormat);

        if ($period !== null) {
            $segments[] = $period;
        }

        $prefix = implode('/', $segments).'/';

        $last = KimCard::where('kim_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('kim_number')
            ->value('kim_number');

        $digits = max($settings->kimNumberSequenceDigits(), 1);
        $sequence = $last ? ((int) substr($last, -$digits)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, $digits, '0', STR_PAD_LEFT);
    }
}
