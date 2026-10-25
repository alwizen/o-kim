<?php

namespace App\Http\Controllers;

use App\Models\KimApplication;
use App\Services\Inspection\InspectionReportService;
use Illuminate\Support\Facades\Gate;

class InspectionReportController extends Controller
{
    /**
     * Cetak/unduh laporan hasil pemeriksaan checklist satu pengajuan.
     */
    public function download(KimApplication $application, InspectionReportService $service)
    {
        Gate::authorize('view', $application);

        return $service->download($application);
    }

    /**
     * Tampilkan laporan di tab baru (inline PDF).
     */
    public function preview(KimApplication $application, InspectionReportService $service)
    {
        Gate::authorize('view', $application);

        return $service->stream($application);
    }
}
