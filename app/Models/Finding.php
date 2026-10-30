<?php

namespace App\Models;

use App\Enums\Department;
use App\Enums\FindingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Finding extends Model
{
    protected $fillable = [
        'kim_application_id', 'inspection_result_id', 'department',
        'description', 'severity', 'status', 'created_by', 'closed_at',
        'dispensation_due_date', 'dispensation_reason', 'dispensated_by', 'dispensated_at',
    ];

    protected function casts(): array
    {
        return [
            'department' => Department::class,
            'status' => FindingStatus::class,
            'closed_at' => 'datetime',
            'dispensation_due_date' => 'date',
            'dispensated_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(KimApplication::class, 'kim_application_id');
    }

    public function inspectionResult(): BelongsTo
    {
        return $this->belongsTo(InspectionResult::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function dispensator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispensated_by');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(FindingAction::class);
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(FindingVerification::class);
    }

    public function dispensations(): HasMany
    {
        return $this->hasMany(FindingDispensation::class)->orderBy('id');
    }

    public function dispensationRequests(): HasMany
    {
        return $this->hasMany(FindingDispensationRequest::class)->orderBy('id');
    }

    /**
     * Permohonan dispensasi yang masih menunggu keputusan FTM.
     */
    public function pendingDispensationRequest(): ?FindingDispensationRequest
    {
        return $this->dispensationRequests
            ->first(fn (FindingDispensationRequest $request) => $request->isPending());
    }

    public function isDispensed(): bool
    {
        return $this->status === FindingStatus::Dispensation;
    }

    /**
     * Dispensasi aktif yang batas waktunya sudah terlewati.
     */
    public function isDispensationExpired(): bool
    {
        return $this->isDispensed()
            && $this->dispensation_due_date !== null
            && $this->dispensation_due_date->lt(Carbon::today());
    }

    /**
     * Temuan yang masih menghalangi approval/penerbitan KIM.
     */
    public function blocksApproval(): bool
    {
        return ! $this->status->isResolved() || $this->isDispensationExpired();
    }
}
