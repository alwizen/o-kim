<?php

namespace App\Http\Controllers;

use App\Models\KimApplication;
use App\Services\Finding\BeritaAcaraService;
use Illuminate\Support\Facades\Gate;

class FindingReportController extends Controller
{
    /**
     * Cetak/unduh Berita Acara tindak lanjut temuan satu pengajuan.
     *
     * Bila seluruh approval lengkap, berkas final (bertanda tangan QR) yang
     * sudah dibekukan dilayani. Sebelum itu, pratinjau tanpa blok tanda tangan.
     */
    public function download(KimApplication $application, BeritaAcaraService $service)
    {
        Gate::authorize('view', $application);

        abort_unless($service->isEligible($application), 404, 'Belum ada temuan yang ditindaklanjuti.');

        return $service->download($application);
    }

    /**
     * Tampilkan Berita Acara di tab baru (inline PDF).
     */
    public function preview(KimApplication $application, BeritaAcaraService $service)
    {
        Gate::authorize('view', $application);

        abort_unless($service->isEligible($application), 404, 'Belum ada temuan yang ditindaklanjuti.');

        return $service->stream($application);
    }
}
