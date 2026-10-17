<?php

namespace App\Services\Kim;

use App\Enums\ApplicationStatus;
use App\Enums\ApprovalDecision;
use App\Enums\Department;
use App\Enums\FindingStatus;
use App\Enums\KimCardStatus;
use App\Models\KimApplication;
use App\Models\KimCard;
use App\Notifications\KimCardIssued;
use App\Services\Approval\ApprovalService;
use App\Services\Setting\SettingsService;
use App\Support\AppNotifier;
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
            $expiresAt = $this->validityService->calculateExpiresAt($application, $issuedAt);

            $card = KimCard::create([
                'kim_application_id' => $application->id,
                'kim_number' => $this->generateKimNumber(),
                'issued_at' => $issuedAt,
                'expires_at' => $expiresAt,
                'qr_token' => Str::random(48),
                'status' => KimCardStatus::Active,
            ]);

            $application->update([
                'status' => ApplicationStatus::Issued,
                'issued_at' => $issuedAt,
                'expires_at' => $expiresAt,
            ]);

            DB::afterCommit(fn () => AppNotifier::notifyTransporter(
                $application->transporter_id,
                new KimCardIssued(
                    $card->kim_number,
                    $application->vehicle->registration_number,
                    $expiresAt->translatedFormat('d M Y'),
                ),
            ));

            return $card;
        });
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

        if ($application->findings()->where('status', '!=', FindingStatus::Verified)->exists()) {
            throw ValidationException::withMessages([
                'findings' => 'Masih ada temuan yang belum selesai.',
            ]);
        }

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
     * Format nomor KIM disusun dari pengaturan sistem:
     * {prefix}/{periode}/{urutan} — contoh KIM/FT-TGL/2026/10/0001
     */
    private function generateKimNumber(): string
    {
        $settings = app(SettingsService::class);

        $segments = [trim($settings->kimNumberPrefix(), '/')];

        $periodFormat = $settings->kimNumberPeriodFormat();

        if ($periodFormat && $periodFormat !== 'none') {
            $segments[] = now()->format($periodFormat);
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
