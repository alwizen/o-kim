<?php

namespace App\Http\Controllers;

use App\Models\KimCard;

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

        return view('kim.validate', [
            'card' => $card,
            'currentStatus' => $card->currentStatus(),
        ]);
    }
}
