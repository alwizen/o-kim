<?php

namespace App\Http\Controllers;

use App\Models\KimCard;
use App\Services\Kim\KimPdfService;
use Illuminate\Support\Facades\Gate;

class KimDownloadController extends Controller
{
    public function __invoke(KimCard $card, KimPdfService $pdfService)
    {
        Gate::authorize('view', $card);

        return $pdfService->download($card);
    }
}
