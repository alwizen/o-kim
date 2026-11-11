<?php

namespace App\Http\Controllers;

use App\Enums\DocumentScanType;
use App\Models\BeritaAcaraDocument;
use App\Models\DocumentScanLog;
use App\Models\DocumentSignature;
use App\Models\KimCard;
use App\Services\Setting\SettingsService;

class DocumentSignatureVerificationController extends Controller
{
    /**
     * Halaman verifikasi publik tanda tangan elektronik via QR.
     *
     * Mendukung tanda tangan Berita Acara maupun Kartu KIM.
     */
    public function show(string $token)
    {
        $signature = DocumentSignature::query()
            ->where('qr_token', $token)
            ->with([
                'signable',
                'user',
            ])
            ->first();

        if ($signature === null) {
            return view('verifikasi.tanda-tangan', ['signature' => null]);
        }

        $document = $signature->signable;
        $application = $signature->application();

        DocumentScanLog::create([
            'document_type' => $document instanceof KimCard
                ? DocumentScanType::Kim
                : DocumentScanType::BeritaAcara,
            'document_signature_id' => $signature->id,
            'kim_card_id' => $document instanceof KimCard ? $document->id : null,
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
            'scanned_at' => now(),
        ]);

        return view('verifikasi.tanda-tangan', [
            'signature' => $signature,
            'document' => $document,
            'application' => $application,
            'documentType' => $document instanceof KimCard ? 'Kartu KIM' : 'Berita Acara',
            'documentNumber' => $signature->documentNumber(),
            'documentHash' => $document instanceof KimCard
                ? $document->pdf_hash
                : ($document instanceof BeritaAcaraDocument ? $document->pdf_hash : null),
            'legalReference' => $document instanceof BeritaAcaraDocument
                ? $document->legal_reference
                : app(SettingsService::class)->legalReference(),
        ]);
    }
}
