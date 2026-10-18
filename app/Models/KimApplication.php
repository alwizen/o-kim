<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
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

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
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
}
