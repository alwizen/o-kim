<?php

namespace App\Http\Controllers;

use App\Models\KimCard;
use App\Services\Kim\KimCardImageService;
use Illuminate\Support\Facades\Gate;

class KimDownloadImageController extends Controller
{
    public function __invoke(KimCard $card, KimCardImageService $imageService)
    {
        Gate::authorize('view', $card);

        return $imageService->download($card);
    }
}
