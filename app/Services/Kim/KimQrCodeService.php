<?php

namespace App\Services\Kim;

use App\Models\KimCard;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class KimQrCodeService
{
    /**
     * QR mengarah ke URL validasi publik — bukan data sensitif.
     */
    public function validationUrl(KimCard $card): string
    {
        return route('kim.validate', ['token' => $card->qr_token]);
    }

    public function renderSvg(KimCard $card, int $size = 200): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size),
            new SvgImageBackEnd
        );

        return (new Writer($renderer))->writeString($this->validationUrl($card));
    }
}
