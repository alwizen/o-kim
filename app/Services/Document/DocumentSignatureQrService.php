<?php

namespace App\Services\Document;

use App\Models\DocumentSignature;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * QR tanda tangan elektronik pada dokumen (Berita Acara maupun Kartu KIM).
 *
 * QR mengarah ke halaman verifikasi publik yang menampilkan identitas penanda
 * tangan, waktu approval, nomor dokumen, dan hash dokumen.
 */
class DocumentSignatureQrService
{
    public function verificationUrl(DocumentSignature $signature): string
    {
        return route('document-signature.verify', ['token' => $signature->qr_token]);
    }

    public function renderSvg(DocumentSignature $signature, int $size = 120): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size),
            new SvgImageBackEnd
        );

        return (new Writer($renderer))->writeString($this->verificationUrl($signature));
    }

    /**
     * Data URI SVG agar dapat disematkan DomPDF tanpa berkas terpisah.
     */
    public function renderDataUri(DocumentSignature $signature, int $size = 120): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($this->renderSvg($signature, $size));
    }
}
