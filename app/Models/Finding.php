<?php

namespace App\Models;

use App\Enums\Department;
use App\Enums\FindingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Finding extends Model
{
    protected $fillable = [
        'kim_application_id', 'inspection_result_id', 'department',
        'description', 'severity', 'status', 'created_by', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'department' => Department::class,
            'status' => FindingStatus::class,
            'closed_at' => 'datetime',
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

    public function actions(): HasMany
    {
        return $this->hasMany(FindingAction::class);
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(FindingVerification::class);
    }
}
