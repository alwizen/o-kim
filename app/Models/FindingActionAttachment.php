<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FindingActionAttachment extends Model
{
    protected $fillable = [
        'finding_action_id', 'file_path', 'original_filename', 'mime_type', 'file_size',
    ];

    public function findingAction(): BelongsTo
    {
        return $this->belongsTo(FindingAction::class);
    }
}
