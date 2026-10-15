<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionResultPoint extends Model
{
    protected $fillable = [
        'inspection_result_id', 'inspection_item_point_id', 'is_checked',
    ];

    protected function casts(): array
    {
        return [
            'is_checked' => 'boolean',
        ];
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(InspectionResult::class, 'inspection_result_id');
    }

    public function point(): BelongsTo
    {
        return $this->belongsTo(InspectionItemPoint::class, 'inspection_item_point_id');
    }
}
