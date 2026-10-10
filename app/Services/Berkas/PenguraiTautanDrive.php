<?php

namespace App\Services\Berkas;

use App\Enums\PenyediaTautan;

/**
 * Mengenali penyedia dan mengekstrak drive_file_id dari URL (tanpa permintaan jaringan).
 */
class PenguraiTautanDrive
{
    private const POLA_FOLDER = '#/drive/(?:u/\d+/)?folders/([A-Za-z0-9_-]+)#';

    private const POLA_BERKAS = [
        '#/file/d/([A-Za-z0-9_-]+)#',
        '#/(?:document|spreadsheets|presentation|forms|drawings)/d/([A-Za-z0-9_-]+)#',
    ];

    /**
     * @return array{penyedia: PenyediaTautan, drive_file_id: string|null, folder: bool}
     */
    public function urai(string $url): array
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $kueri);

        $penyedia = match (true) {
            $host === 'drive.google.com' => PenyediaTautan::GoogleDrive,
            $host === 'docs.google.com' => PenyediaTautan::GoogleDocs,
            $host === 'unsil.ac.id' || str_ends_with($host, '.unsil.ac.id') => PenyediaTautan::Unsil,
            $host === 'onedrive.live.com' || str_ends_with($host, '.sharepoint.com') => PenyediaTautan::Onedrive,
            default => PenyediaTautan::Lainnya,
        };

        if (! in_array($penyedia, [PenyediaTautan::GoogleDrive, PenyediaTautan::GoogleDocs], true)) {
            return ['penyedia' => $penyedia, 'drive_file_id' => null, 'folder' => false];
        }

        if (preg_match(self::POLA_FOLDER, $path, $cocok)) {
            return ['penyedia' => $penyedia, 'drive_file_id' => null, 'folder' => true];
        }

        foreach (self::POLA_BERKAS as $pola) {
            if (preg_match($pola, $path, $cocok)) {
                return ['penyedia' => $penyedia, 'drive_file_id' => $cocok[1], 'folder' => false];
            }
        }

        // open?id=<id>, uc?id=<id>
        $id = is_string($kueri['id'] ?? null) && preg_match('/^[A-Za-z0-9_-]+$/', $kueri['id']) ? $kueri['id'] : null;

        return ['penyedia' => $penyedia, 'drive_file_id' => $id, 'folder' => false];
    }
}
