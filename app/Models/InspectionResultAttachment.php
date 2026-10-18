<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionResultAttachment extends Model
{
    protected $fillable = [
        'inspection_result_id', 'uploaded_by',
        'file_path', 'original_filename', 'mime_type', 'file_size',
    ];

    public function result(): BelongsTo
    {
        return $this->belongsTo(InspectionResult::class, 'inspection_result_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
