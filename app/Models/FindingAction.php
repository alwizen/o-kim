<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FindingAction extends Model
{
    protected $fillable = ['finding_id', 'submitted_by', 'description'];

    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(FindingActionAttachment::class);
    }
}
