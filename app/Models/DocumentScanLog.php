<?php

namespace App\Models;

use App\Enums\DocumentScanType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat pemindaian QR verifikasi dokumen (Berita Acara atau Kartu KIM).
 *
 * `document_signature_id` terisi bila yang dipindai adalah QR tanda tangan
 * elektronik; `kim_card_id` terisi bila yang dipindai adalah QR validasi kartu.
 */
class DocumentScanLog extends Model
{
    protected $fillable = [
        'document_type',
        'document_signature_id',
        'kim_card_id',
        'ip_address',
        'user_agent',
        'scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => DocumentScanType::class,
            'scanned_at' => 'datetime',
        ];
    }

    public function signature(): BelongsTo
    {
        return $this->belongsTo(DocumentSignature::class, 'document_signature_id');
    }

    public function kimCard(): BelongsTo
    {
        return $this->belongsTo(KimCard::class);
    }

    /**
     * Nomor dokumen yang diverifikasi (No. BA atau Nomor KIM).
     */
    public function documentNumber(): string
    {
        if ($this->signature !== null) {
            return (string) $this->signature->documentNumber();
        }

        return (string) ($this->kimCard?->kim_number ?? '—');
    }

    /**
     * Nomor polisi terkait dokumen.
     */
    public function registrationNumber(): string
    {
        $application = $this->signature?->application() ?? $this->kimCard?->application;

        return (string) ($application?->vehicle?->registration_number ?? '—');
    }

    /**
     * Identitas penanda tangan / penerbit.
     */
    public function signerName(): string
    {
        return (string) ($this->signature?->name ?? 'Kartu KIM diterbitkan sistem');
    }
}
