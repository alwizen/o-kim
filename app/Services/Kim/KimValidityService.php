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
     * Batas dispensasi (bila ada) ikut membatasi masa berlaku KIM sementara.
     *
     * @throws ValidationException jika dokumen wajib tidak valid pada saat penerbitan
     */
    public function calculateExpiresAt(KimApplication $application, Carbon $issuedAt, ?Carbon $dispensationDueDate = null): Carbon
    {
        return $this->resolveValidity($application, $issuedAt, $dispensationDueDate)['expires_at'];
    }

    /**
     * Label sumber masa berlaku, siap ditempel di samping tanggal pada dokumen.
     *
     * Contoh: " (Dokumen TERA)" bila dibatasi dokumen wajib paling awal,
     * atau " (Batas Maksimum 6 Bulan)" bila batas pengaturan lebih dulu.
     */
    public function validityLabel(KimApplication $application): string
    {
        $source = $this->resolveValidity($application, $application->kimCard?->issued_at ?? now());

        return match ($source['basis']) {
            'document' => $source['document_type'] !== null
                ? ' (Dokumen '.$source['document_type'].')'
                : '',
            'dispensation' => ' (Batas Dispensasi)',
            default => ' (Batas Maksimum '.$this->settings->kimValidityMonths().' Bulan)',
        };
    }

    /**
     * Hitung tanggal kedaluwarsa beserta sumber yang menentukannya.
     *
     * `basis` bernilai:
     * - `document`     — dokumen wajib dengan masa berlaku paling awal
     * - `max_months`   — batas maksimum masa berlaku dari pengaturan
     * - `dispensation` — batas dispensasi FTM (KIM sementara)
     *
     * @return array{basis: string, document_type: string|null, expires_at: Carbon}
     *
     * @throws ValidationException jika dokumen wajib tidak valid pada saat penerbitan
     */
    public function resolveValidity(KimApplication $application, Carbon $issuedAt, ?Carbon $dispensationDueDate = null): array
    {
        $requiredDocs = $application->vehicle->documents()
            ->whereNull('replaced_by_id')
            ->where('verification_status', DocumentVerificationStatus::Verified)
            ->whereHas('documentType', fn ($q) => $q->where('is_required', true)->where('is_active', true))
            ->with('documentType')
            ->get();

        $earliestExpiry = null;
        $earliestType = null;

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
                $earliestType = $document->documentType->name;
            }
        }

        $maxExpiry = $issuedAt->copy()->addMonths($this->settings->kimValidityMonths());

        $usesDocument = $earliestExpiry !== null && $earliestExpiry->lt($maxExpiry);

        $basis = $usesDocument ? 'document' : 'max_months';
        $expiry = $usesDocument ? $earliestExpiry : $maxExpiry;
        $documentType = $usesDocument ? $earliestType : null;

        // KIM dispensasi tidak boleh melewati batas dispensasi terawal.
        if ($dispensationDueDate !== null && $dispensationDueDate->lt($expiry)) {
            $expiry = $dispensationDueDate->copy();
            $basis = 'dispensation';
            $documentType = null;
        }

        return [
            'basis' => $basis,
            'document_type' => $documentType,
            'expires_at' => $expiry,
        ];
    }
}
