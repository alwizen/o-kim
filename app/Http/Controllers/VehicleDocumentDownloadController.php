<?php

namespace App\Http\Controllers;

use App\Models\VehicleDocument;
use App\Services\Document\VehicleDocumentService;
use Illuminate\Support\Facades\Gate;

class VehicleDocumentDownloadController extends Controller
{
    public function __invoke(VehicleDocument $document, VehicleDocumentService $service)
    {
        Gate::authorize('view', $document);

        return $service->download($document);
    }
}
