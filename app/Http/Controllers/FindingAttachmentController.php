<?php

namespace App\Http\Controllers;

use App\Models\FindingActionAttachment;
use App\Services\Finding\FindingService;
use Illuminate\Support\Facades\Gate;

class FindingAttachmentController extends Controller
{
    public function preview(FindingActionAttachment $attachment, FindingService $service)
    {
        Gate::authorize('view', $attachment);

        return $service->attachmentPreview($attachment);
    }

    public function download(FindingActionAttachment $attachment, FindingService $service)
    {
        Gate::authorize('view', $attachment);

        return $service->attachmentDownload($attachment);
    }
}
