<?php

namespace App\Models;

use App\Enums\KimCardStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KimCard extends Model
{
    protected $fillable = [
        'kim_application_id', 'kim_number', 'issued_at',
        'expires_at', 'qr_token', 'pdf_path', 'status',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'expires_at' => 'date',
            'status' => KimCardStatus::class,
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(KimApplication::class, 'kim_application_id');
    }

    public function currentStatus(): KimCardStatus
    {
        if ($this->status === KimCardStatus::Revoked) {
            return KimCardStatus::Revoked;
        }

        if ($this->expires_at->isPast()) {
            return KimCardStatus::Expired;
        }

        return KimCardStatus::Active;
    }
}
