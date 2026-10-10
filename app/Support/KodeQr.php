<?php

namespace App\Support;

use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

/**
 * QR dibangkitkan on-the-fly sebagai data URI PNG (tidak disimpan, STANDAR-TEKNIS §1a.6).
 */
final class KodeQr
{
    public static function dataUri(string $isi, int $ukuran = 160): string
    {
        $qr = new QrCode(data: $isi, errorCorrectionLevel: ErrorCorrectionLevel::Medium, size: $ukuran, margin: 4);

        return (new PngWriter)->write($qr)->getDataUri();
    }
}
