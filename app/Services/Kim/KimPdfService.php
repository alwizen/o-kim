<?php

namespace App\Services\Kim;

use App\Enums\Department;
use App\Models\KimCard;
use App\Services\Document\DocumentSignatureService;
use App\Services\Setting\SettingsService;
use App\Support\SafeFilename;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class KimPdfService
{
    private function disk(): string
    {
        return config('media.disk');
    }

    public function generate(KimCard $card): string
    {
        $card->load('application.vehicle', 'application.transporter', 'signatures');

        // Dokumen lama yang dibuat sebelum fitur tanda tangan ada: lengkapi dulu.
        app(DocumentSignatureService::class)->ensureFor($card, $card->application);
        $card->load('signatures');

        $qrSvg = app(SettingsService::class)->kimQrEnabled()
            ? app(KimQrCodeService::class)->renderSvg($card, 180)
            : null;

        $signatureBlocks = app(DocumentSignatureService::class)->blocks(
            $card->signatures,
            $this->signatureDepartments($card),
            app(SettingsService::class)->kimQrEnabled(),
        );

        $pdf = Pdf::loadView('kim.card', [
            'card' => $card,
            'qrSvg' => $qrSvg,
            'validityLabel' => app(KimValidityService::class)->validityLabel($card->application),
            'signatureBlocks' => $signatureBlocks,
        ])->setPaper('a5', 'landscape');
        $binary = $pdf->output();

        $path = 'kim-cards/'.$this->safeFilename($card->kim_number);

        Storage::disk($this->disk())->put($path, $binary);

        $card->update([
            'pdf_path' => $path,
            'pdf_hash' => hash('sha256', $binary),
        ]);

        return $path;
    }

    public function download(KimCard $card)
    {
        if (! $card->pdf_path
            || ! $card->pdf_hash
            || ! Storage::disk($this->disk())->exists($card->pdf_path)) {
            $this->generate($card);
            $card->refresh();
        }

        return Storage::disk($this->disk())->download(
            $card->pdf_path,
            $this->safeFilename($card->kim_number)
        );
    }

    /**
     * Fungsi yang tampil pada blok tanda tangan kartu.
     *
     * KIM sementara hanya ditandatangani FTM; KIM biasa memuat lima fungsi.
     *
     * @return array<int, Department>
     */
    private function signatureDepartments(KimCard $card): array
    {
        return $card->is_dispensation
            ? [Department::Ftm]
            : Department::approvalSteps();
    }

    /**
     * Nama berkas PDF yang aman (dengan ekstensi .pdf).
     */
    private function safeFilename(string $kimNumber): string
    {
        return SafeFilename::make($kimNumber.'.pdf', 'kim');
    }
}
