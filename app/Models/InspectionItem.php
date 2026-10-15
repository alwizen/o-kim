<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InspectionItem extends Model
{
    protected $fillable = [
        'inspection_checklist_id', 'item_code', 'label', 'description',
        'sort_order', 'is_required', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(InspectionChecklist::class, 'inspection_checklist_id');
    }

    public function points(): HasMany
    {
        return $this->hasMany(InspectionItemPoint::class)->orderBy('sort_order');
    }
}
