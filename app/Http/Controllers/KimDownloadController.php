<?php

namespace App\Http\Controllers;

use App\Models\KimCard;
use App\Services\Kim\KimPdfService;

class KimDownloadController extends Controller
{
    public function __invoke(KimCard $card, KimPdfService $pdfService)
    {
        $user = auth()->user();

        $isOwner = $user->transporters()
            ->where('transporters.id', $card->application->transporter_id)
            ->exists();

        abort_unless($isOwner || $user->can('view_kim_cards') || $user->can('access_admin_panel'), 403);

        return $pdfService->download($card);
    }
}
