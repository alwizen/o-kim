<?php

namespace App\Models;

use App\Enums\KimCardStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

class KimCard extends Model
{
    protected $fillable = [
        'kim_application_id', 'kim_number', 'issued_at',
        'expires_at', 'qr_token', 'pdf_path', 'pdf_hash', 'image_path', 'status',
        'is_dispensation', 'dispensation_expires_at', 'dispensation_lapsed_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'expires_at' => 'date',
            'status' => KimCardStatus::class,
            'is_dispensation' => 'boolean',
            'dispensation_expires_at' => 'date',
            'dispensation_lapsed_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(KimApplication::class, 'kim_application_id');
    }

    /**
     * Tanda tangan elektronik lima fungsi pada dokumen KIM.
     */
    public function signatures(): MorphMany
    {
        return $this->morphMany(DocumentSignature::class, 'signable')->orderBy('sort_order');
    }

    public function currentStatus(): KimCardStatus
    {
        if ($this->status === KimCardStatus::Revoked) {
            return KimCardStatus::Revoked;
        }

        // KIM dispensasi otomatis tidak berlaku setelah batas dispensasi.
        if ($this->isDispensationLapsed()) {
            return KimCardStatus::Expired;
        }

        if ($this->expires_at->isPast()) {
            return KimCardStatus::Expired;
        }

        return KimCardStatus::Active;
    }

    /**
     * Batas dispensasi sudah terlewati (berlaku sampai akhir tanggal batas).
     */
    public function isDispensationLapsed(): bool
    {
        return (bool) $this->is_dispensation
            && $this->dispensation_expires_at !== null
            && $this->dispensation_expires_at->lt(Carbon::today());
    }
}
