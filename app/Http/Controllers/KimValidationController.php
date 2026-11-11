<?php

namespace App\Http\Controllers;

use App\Enums\DocumentScanType;
use App\Models\DocumentScanLog;
use App\Models\KimCard;
use App\Services\Kim\KimValidityService;

class KimValidationController extends Controller
{
    /**
     * Halaman validasi publik via QR Code.
     * Hanya menampilkan informasi minimum sesuai PRD bagian 9.
     */
    public function show(string $token)
    {
        $card = KimCard::where('qr_token', $token)
            ->with('application.vehicle')
            ->first();

        if (! $card) {
            return view('kim.validate', ['card' => null]);
        }

        DocumentScanLog::create([
            'document_type' => DocumentScanType::Kim,
            'kim_card_id' => $card->id,
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
            'scanned_at' => now(),
        ]);

        return view('kim.validate', [
            'card' => $card,
            'currentStatus' => $card->currentStatus(),
            'validityLabel' => app(KimValidityService::class)->validityLabel($card->application),
        ]);
    }
}
