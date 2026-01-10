<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\Png;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCodeService
{
    public function generatePng(string $url, int $size = 300): string
    {
        $renderer = new Png();
        $renderer->setHeight($size);
        $renderer->setWidth($size);

        $writer = new Writer($renderer);

        return $writer->writeString($url);
    }
}
