<?php

namespace App\Models;

use App\Enums\ApprovalDecision;
use App\Enums\Department;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Approval extends Model
{
    protected $fillable = [
        'kim_application_id', 'department', 'decision',
        'approved_by', 'notes', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'department' => Department::class,
            'decision' => ApprovalDecision::class,
            'decided_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(KimApplication::class, 'kim_application_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
