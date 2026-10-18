<?php

namespace App\Http\Controllers;

use App\Models\InspectionResultAttachment;
use App\Services\Inspection\InspectionAttachmentService;
use Illuminate\Support\Facades\Gate;

class InspectionResultAttachmentController extends Controller
{
    public function preview(InspectionResultAttachment $attachment, InspectionAttachmentService $service)
    {
        Gate::authorize('view', $attachment);

        return $service->preview($attachment);
    }

    public function download(InspectionResultAttachment $attachment, InspectionAttachmentService $service)
    {
        Gate::authorize('view', $attachment);

        return $service->download($attachment);
    }
}
