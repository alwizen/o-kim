<?php

namespace App\Models;

use App\Enums\Department;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Tanda tangan elektronik pada dokumen (Berita Acara maupun Kartu KIM).
 *
 * Identitas penanda tangan disalin saat dokumen dibekukan agar tetap historis,
 * dan setiap baris memiliki token QR unik untuk halaman verifikasi publik.
 */
class DocumentSignature extends Model
{
    protected $fillable = [
        'signable_type',
        'signable_id',
        'department',
        'user_id',
        'name',
        'jabatan',
        'decided_at',
        'ip_address',
        'user_agent',
        'qr_token',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'department' => Department::class,
            'decided_at' => 'datetime',
        ];
    }

    public function signable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(DocumentScanLog::class);
    }

    /**
     * Nomor dokumen induk (No. BA atau Nomor KIM).
     */
    public function documentNumber(): string
    {
        return match (true) {
            $this->signable instanceof BeritaAcaraDocument => (string) $this->signable->number,
            $this->signable instanceof KimCard => (string) $this->signable->kim_number,
            default => '—',
        };
    }

    /**
     * Pengajuan terkait dokumen induk.
     */
    public function application(): ?KimApplication
    {
        return match (true) {
            $this->signable instanceof BeritaAcaraDocument => $this->signable->application,
            $this->signable instanceof KimCard => $this->signable->application,
            default => null,
        };
    }
}
