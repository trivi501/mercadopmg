<?php

namespace App\Services;

use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCodeService
{
    public function svg(string $content, int $size = 200): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd));

        return $writer->writeString($content);
    }

    /**
     * PNG como data URI (para PDF e impresión térmica).
     */
    public function pngDataUri(string $content, int $size = 200): string
    {
        $writer = new Writer(new GDLibRenderer($size, 1));

        return 'data:image/png;base64,'.base64_encode($writer->writeString($content));
    }
}
