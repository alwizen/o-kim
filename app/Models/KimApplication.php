<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\FindingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class KimApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_number',
        'vehicle_id',
        'transporter_id',
        'application_type',
        'status',
        'submitted_at',
        'submitted_by',
        'inspection_scheduled_at',
        'inspection_scheduled_by',
        'final_approved_at',
        'issued_at',
        'expires_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'application_type' => ApplicationType::class,
            'status' => ApplicationStatus::class,
            'submitted_at' => 'datetime',
            'inspection_scheduled_at' => 'datetime',
            'final_approved_at' => 'datetime',
            'issued_at' => 'datetime',
            'expires_at' => 'date',
        ];
    }

    /**
     * Kendaraan terkait, termasuk yang sudah dihapus lunak.
     *
     * Riwayat pengajuan/KIM harus tetap menampilkan data kendaraan walau
     * kendaraannya kemudian dihapus, agar dokumen terbit tetap dapat diaudit.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function transporter(): BelongsTo
    {
        return $this->belongsTo(Transporter::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function scheduler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspection_scheduled_by');
    }

    /**
     * Jadwal pemeriksaan sudah ditetapkan petugas.
     */
    public function isInspectionScheduled(): bool
    {
        return $this->inspection_scheduled_at !== null;
    }

    /**
     * Pemeriksaan hanya dapat dimulai setelah jadwal ditetapkan DAN tanggalnya sudah tiba.
     */
    public function isInspectionDue(): bool
    {
        return $this->inspection_scheduled_at !== null
            && $this->inspection_scheduled_at->lte(now());
    }

    public function inspectionResults(): HasMany
    {
        return $this->hasMany(InspectionResult::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    public function kimCard(): HasOne
    {
        return $this->hasOne(KimCard::class);
    }

    public function beritaAcara(): HasOne
    {
        return $this->hasOne(BeritaAcaraDocument::class);
    }

    /**
     * KIM sementara masih dapat diterbitkan: dispensasi aktif dan belum ada kartu.
     *
     * Dipakai tombol "Terbitkan KIM Sementara" setelah FTM menyetujui dispensasi.
     */
    public function canIssueProvisional(): bool
    {
        if ($this->kimCard !== null) {
            return false;
        }

        if (in_array($this->status, [ApplicationStatus::Issued, ApplicationStatus::Cancelled, ApplicationStatus::Rejected], true)) {
            return false;
        }

        return $this->hasActiveDispensation();
    }

    /**
     * Pengajuan layak diajukan ulang sebagai Perpanjangan.
     *
     * Terjadi ketika KIM sementara sudah tidak berlaku: kartu ditandai dispensasi
     * dan batas waktunya telah lewat. Pengajuan lama tetap berstatus Issued
     * (terminal), sehingga perbaikan dilakukan lewat pengajuan baru.
     */
    public function canBeRenewed(): bool
    {
        $card = $this->kimCard;

        if ($card === null || ! $card->is_dispensation) {
            return false;
        }

        return $card->dispensation_lapsed_at !== null
            || ($card->dispensation_expires_at !== null && $card->dispensation_expires_at->isPast());
    }

    /**
     * Pengajuan menerbitkan KIM sementara bila memiliki temuan dispensasi aktif
     * (batas waktunya belum lewat). Kondisi ini sama dengan yang dipakai
     * KimIssuanceService saat menandai kartu sebagai dispensasi.
     */
    public function hasActiveDispensation(): bool
    {
        return $this->findings()
            ->where('status', FindingStatus::Dispensation->value)
            ->whereNotNull('dispensation_due_date')
            ->whereDate('dispensation_due_date', '>=', now()->toDateString())
            ->exists();
    }

    /**
     * Temuan yang masih menghalangi approval: belum selesai diverifikasi atau
     * dispensasinya sudah melewati batas waktu.
     */
    public function hasBlockingFindings(): bool
    {
        return $this->findings()
            ->whereNotIn('status', [FindingStatus::Verified->value])
            ->where(function (Builder $query) {
                $query->where('status', '!=', FindingStatus::Dispensation->value)
                    ->orWhereDate('dispensation_due_date', '<', now()->toDateString());
            })
            ->exists();
    }
}
