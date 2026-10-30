<?php

namespace App\Models;

use App\Enums\DispensationAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FindingDispensation extends Model
{
    protected $fillable = [
        'finding_id', 'user_id', 'action', 'due_date', 'reason', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'action' => DispensationAction::class,
            'due_date' => 'date',
        ];
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
