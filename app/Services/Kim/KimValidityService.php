<?php

namespace App\Services\Kim;

use App\Enums\DocumentVerificationStatus;
use App\Models\KimApplication;
use App\Services\Setting\SettingsService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class KimValidityService
{
    /**
     * Batas maksimum masa berlaku KIM dalam bulan diambil dari pengaturan sistem
     * (default 6 bulan). Nilai ini dapat diubah admin melalui halaman Pengaturan.
     */
    public const DEFAULT_VALIDITY_MONTHS = 6;

    public function __construct(private SettingsService $settings) {}

    /**
     * Hitung tanggal kedaluwarsa KIM.
     *
     * Aturan (PRD bagian 8, rancangan):
     * - KIM mengikuti tanggal kedaluwarsa paling awal dari dokumen wajib yang aktif.
     * - Dibatasi maksimum masa berlaku dari pengaturan sistem (default 6 bulan).
     *
     * @throws ValidationException jika dokumen wajib tidak valid pada saat penerbitan
     */
    public function calculateExpiresAt(KimApplication $application, Carbon $issuedAt): Carbon
    {
        $requiredDocs = $application->vehicle->documents()
            ->whereNull('replaced_by_id')
            ->where('verification_status', DocumentVerificationStatus::Verified)
            ->whereHas('documentType', fn ($q) => $q->where('is_required', true)->where('is_active', true))
            ->with('documentType')
            ->get();

        $earliestExpiry = null;

        foreach ($requiredDocs as $document) {
            if (! $document->documentType->has_expiry_date) {
                continue;
            }

            if (! $document->expires_at) {
                throw ValidationException::withMessages([
                    'documents' => "Dokumen {$document->documentType->name} tidak memiliki tanggal kedaluwarsa.",
                ]);
            }

            if ($document->expires_at->lte($issuedAt)) {
                throw ValidationException::withMessages([
                    'documents' => "Dokumen {$document->documentType->name} sudah kedaluwarsa pada tanggal penerbitan.",
                ]);
            }

            if (! $earliestExpiry || $document->expires_at->lt($earliestExpiry)) {
                $earliestExpiry = $document->expires_at->copy();
            }
        }

        $maxExpiry = $issuedAt->copy()->addMonths($this->settings->kimValidityMonths());

        if (! $earliestExpiry) {
            return $maxExpiry;
        }

        return $earliestExpiry->lt($maxExpiry) ? $earliestExpiry : $maxExpiry;
    }
}
