<?php

namespace App\Http\Controllers;

use App\Models\KimApplication;
use App\Services\Finding\FindingReportService;
use Illuminate\Support\Facades\Gate;

class FindingReportController extends Controller
{
    /**
     * Cetak/unduh Berita Acara tindak lanjut temuan satu pengajuan.
     */
    public function download(KimApplication $application, FindingReportService $service)
    {
        Gate::authorize('view', $application);

        abort_unless($service->isEligible($application), 404, 'Belum ada temuan yang ditindaklanjuti.');

        return $service->download($application);
    }

    /**
     * Tampilkan Berita Acara di tab baru (inline PDF).
     */
    public function preview(KimApplication $application, FindingReportService $service)
    {
        Gate::authorize('view', $application);

        abort_unless($service->isEligible($application), 404, 'Belum ada temuan yang ditindaklanjuti.');

        return $service->stream($application);
    }
}
