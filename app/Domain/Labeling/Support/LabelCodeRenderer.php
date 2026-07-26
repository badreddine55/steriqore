<?php

namespace App\Domain\Labeling\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Com\Tecnick\Barcode\Barcode;

/**
 * Both codes encode the same opaque payload (urn:steriqore:label:<uuid> —
 * never patient data, never a secret, per CLAUDE.md). SVG output, not PNG:
 * it's what the label PDF sheet embeds directly as inline markup for
 * Browsershot/Chromium to rasterize, and it's what the standalone code
 * endpoints return too — one code path either way, no Imagick/GD needed
 * just to serve a 2D code.
 */
class LabelCodeRenderer
{
    public static function qrSvg(string $payload): string
    {
        $renderer = new ImageRenderer(new RendererStyle(300), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString($payload);
    }

    public static function dataMatrixSvg(string $payload): string
    {
        $barcode = new Barcode;

        return $barcode->getBarcodeObj(
            type: 'DATAMATRIX',
            code: $payload,
            width: -4,
            height: -4,
            color: 'black',
        )->setBackgroundColor('white')->getSvgCode();
    }
}
