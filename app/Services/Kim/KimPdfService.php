<?php

namespace App\Services\Kim;

use App\Models\KimCard;
use App\Support\SafeFilename;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class KimPdfService
{
    private const DISK = 'local';

    public function generate(KimCard $card): string
    {
        $qrSvg = app(KimQrCodeService::class)->renderSvg($card, 180);

        $pdf = Pdf::loadView('kim.card', [
            'card' => $card->load('application.vehicle', 'application.transporter'),
            'qrSvg' => $qrSvg,
        ])->setPaper('a5', 'landscape');

        $path = 'kim-cards/'.$this->safeFilename($card->kim_number);

        Storage::disk(self::DISK)->put($path, $pdf->output());

        $card->update(['pdf_path' => $path]);

        return $path;
    }

    public function download(KimCard $card)
    {
        if (! $card->pdf_path || ! Storage::disk(self::DISK)->exists($card->pdf_path)) {
            $this->generate($card);
            $card->refresh();
        }

        return Storage::disk(self::DISK)->download(
            $card->pdf_path,
            $this->safeFilename($card->kim_number)
        );
    }

    /**
     * Nama berkas PDF yang aman (dengan ekstensi .pdf).
     */
    private function safeFilename(string $kimNumber): string
    {
        return SafeFilename::make($kimNumber.'.pdf', 'kim');
    }
}
