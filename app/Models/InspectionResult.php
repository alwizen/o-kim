<?php

namespace App\Models;

use App\Enums\InspectionResultValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InspectionResult extends Model
{
    protected $fillable = [
        'kim_application_id', 'inspection_item_id', 'inspected_by',
        'result', 'notes', 'inspected_at',
    ];

    protected function casts(): array
    {
        return [
            'result' => InspectionResultValue::class,
            'inspected_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(KimApplication::class, 'kim_application_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InspectionItem::class, 'inspection_item_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function finding(): HasOne
    {
        return $this->hasOne(Finding::class);
    }

    public function pointResults(): HasMany
    {
        return $this->hasMany(InspectionResultPoint::class);
    }

    /**
     * Peta status centang sub-butir: [inspection_item_point_id => bool]
     *
     * @return array<int, bool>
     */
    public function checkedPointMap(): array
    {
        return $this->pointResults()
            ->pluck('is_checked', 'inspection_item_point_id')
            ->map(fn ($value) => (bool) $value)
            ->all();
    }
}
