<?php

namespace App\Services\Document;

use App\Enums\DocumentVerificationStatus;
use App\Models\VehicleDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class DocumentExpiryService
{
    /**
     * Ambang batas peringatan dokumen (dalam hari).
     * Nilai ini rancangan awal dan dapat disesuaikan setelah konfirmasi kebijakan terminal.
     */
    public const WARNING_THRESHOLD_DAYS = 60;

    /**
     * Query dokumen aktif (versi terbaru, bukan ditolak) milik kendaraan aktif.
     *
     * @param  array<int>  $transporterIds  Batasi ke perusahaan tertentu (isolasi data transportir)
     */
    public function expiringDocumentsQuery(array $transporterIds = []): Builder
    {
        $threshold = now()->addDays(self::WARNING_THRESHOLD_DAYS);

        // Ambang aktif mulai dari H-7 agar dokumen yang baru lewat tetap tampil sebagai peringatan.
        $graceStart = now()->subDays(7);

        return VehicleDocument::query()
            ->select('vehicle_documents.*')
            ->join('vehicles', 'vehicles.id', '=', 'vehicle_documents.vehicle_id')
            ->join('transporters', 'transporters.id', '=', 'vehicles.transporter_id')
            ->join('document_types', 'document_types.id', '=', 'vehicle_documents.document_type_id')
            ->whereNull('vehicle_documents.replaced_by_id')
            ->whereNull('vehicles.deleted_at')
            ->where('document_types.is_active', true)
            ->where('vehicles.status', 'active')
            ->whereNotIn('vehicle_documents.verification_status', [
                DocumentVerificationStatus::Rejected->value,
            ])
            ->whereNotNull('vehicle_documents.expires_at')
            ->whereBetween('vehicle_documents.expires_at', [$graceStart->toDateString(), $threshold->toDateString()])
            ->when($transporterIds !== [], fn ($query) => $query->whereIn('vehicles.transporter_id', $transporterIds))
            ->orderBy('vehicle_documents.expires_at');
    }

    /**
     * Jumlah dokumen yang sudah kedaluwarsa.
     *
     * @param  array<int>  $transporterIds
     */
    public function expiredCount(array $transporterIds = []): int
    {
        return VehicleDocument::query()
            ->join('vehicles', 'vehicles.id', '=', 'vehicle_documents.vehicle_id')
            ->whereNull('vehicle_documents.replaced_by_id')
            ->whereNull('vehicles.deleted_at')
            ->where('vehicles.status', 'active')
            ->whereNotNull('vehicle_documents.expires_at')
            ->where('vehicle_documents.expires_at', '<', now()->startOfDay())
            ->when($transporterIds !== [], fn ($query) => $query->whereIn('vehicles.transporter_id', $transporterIds))
            ->count();
    }

    /**
     * Jumlah dokumen yang mendekati kedaluwarsa (belum lewat).
     *
     * @param  array<int>  $transporterIds
     */
    public function expiringCount(array $transporterIds = []): int
    {
        return $this->expiringDocumentsQuery($transporterIds)
            ->where('vehicle_documents.expires_at', '>=', now()->startOfDay())
            ->count();
    }

    public static function daysUntil(Carbon $date): int
    {
        return (int) now()->startOfDay()->diffInDays($date->copy()->startOfDay(), false);
    }
}
