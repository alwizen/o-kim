<?php

namespace App\Models;

use App\Enums\Department;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InspectionChecklist extends Model
{
    protected $fillable = [
        'department', 'code', 'title', 'description', 'is_required', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'department' => Department::class,
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(InspectionItem::class)->orderBy('sort_order');
    }
}
