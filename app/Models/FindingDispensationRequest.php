<?php

namespace App\Models;

use App\Enums\DispensationRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Permohonan dispensasi temuan yang diajukan transportir kepada FTM.
 *
 * Transportir mengajukan alasan penundaan perbaikan; FTM memutuskan dengan
 * menetapkan batas waktu perbaikan (saat disetujui) atau alasan penolakan.
 */
class FindingDispensationRequest extends Model
{
    protected $fillable = [
        'finding_id', 'requested_by', 'reason', 'status',
        'due_date', 'decided_by', 'decided_at', 'decision_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => DispensationRequestStatus::class,
            'due_date' => 'date',
            'decided_at' => 'datetime',
        ];
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->status === DispensationRequestStatus::Pending;
    }
}
