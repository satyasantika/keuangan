<?php

use Symfony\Component\Yaml\Yaml;

/**
 * Berkas Docker produksi.
 */
$akar = dirname(__DIR__, 2);

test('.dockerignore mengecualikan rahasia, dump, dan berkas sementara', function () use ($akar) {
    $baris = array_map('trim', file($akar.'/.dockerignore'));

    expect($baris)->toContain('.env', '.env.*', 'storage/app/tmp/*', 'docs/referensi/rahasia', '*.sql.gz', 'vendor', 'node_modules', 'tests')
        ->not->toContain('!.env');
});

/**
 * Produksi Profil Ringan (STANDAR-TEKNIS §1b dan §7): MariaDB NATIVE di host, tanpa container database,
 * Redis, atau Horizon; setiap container berbatas memori dan lognya dirotasi.
 *
 * @return array<string, mixed>
 */
function komposProduksi(): array
{
    return Yaml::parseFile(dirname(__DIR__, 2).'/docker-compose.prod.yml');
}

test('compose produksi hanya memuat app, web, scheduler, dan backup', function () {
    expect(array_keys(komposProduksi()['services']))->toEqualCanonicalizing(['php', 'web', 'scheduler', 'backup']);
});

test('compose produksi tidak memuat container database, redis, atau queue', function () {
    $kompos = komposProduksi();

    foreach (['queue', 'redis', 'db', 'mysql', 'mariadb', 'meilisearch'] as $layanan) {
        expect($kompos['services'])->not->toHaveKey($layanan);
    }

    foreach (['redis', 'mysql', 'redis-data', 'db-data'] as $volume) {
        expect($kompos['volumes'] ?? [])->not->toHaveKey($volume);
    }

    // hasil parse (tanpa komentar): tidak ada image/perintah Redis atau Horizon
    expect(json_encode($kompos))->not->toMatch('/horizon|redis/i');
});

test('image mariadb hanya dipakai backup (klien dump), bukan server basis data', function () {
    foreach (komposProduksi()['services'] as $nama => $layanan) {
        expect((string) ($layanan['image'] ?? ''))->not->toMatch('/^(mysql|mariadb)(:|$)/');
    }

    $dockerfile = (string) file_get_contents(dirname(__DIR__, 2).'/docker/backup/Dockerfile');

    expect($dockerfile)->toContain('mariadb-client')->not->toContain('mysql-client');
});

test('container aplikasi menjangkau basis data host lewat host-gateway', function () {
    foreach (['php', 'scheduler', 'backup'] as $nama) {
        expect(komposProduksi()['services'][$nama]['extra_hosts'])->toContain('host.docker.internal:host-gateway');
    }

    expect(file_get_contents(dirname(__DIR__, 2).'/.env.production.example'))
        ->toContain("\nDB_CONNECTION=mariadb\n")
        ->toContain("\nDB_HOST=host.docker.internal\n")
        ->toContain("\nDB_DATABASE=db_keuangan\n")
        ->not->toContain('DB_ROOT_PASSWORD');
});

test('penjadwal menjadi pemicu worker tanpa bergantung pada layanan lain', function () {
    $penjadwal = komposProduksi()['services']['scheduler'];

    expect($penjadwal['command'])->toBe(['php', 'artisan', 'schedule:work'])
        ->and($penjadwal)->not->toHaveKey('depends_on')
        ->and($penjadwal['stop_grace_period'])->toBe('60s');
});

test('setiap container berbatas memori dan lognya dirotasi 10m x 3', function () {
    foreach (komposProduksi()['services'] as $layanan) {
        expect($layanan)->toHaveKey('mem_limit')
            ->and($layanan['logging']['driver'])->toBe('json-file')
            ->and($layanan['logging']['options'])->toBe(['max-size' => '10m', 'max-file' => '3']);
    }
});

test('Dockerfile berbasis PHP 8.5 tanpa phpredis dan skrip cadangan memakai mariadb-dump', function () use ($akar) {
    $dockerfile = (string) file_get_contents($akar.'/docker/Dockerfile');
    $backup = (string) file_get_contents($akar.'/docker/backup/backup.sh');

    expect($dockerfile)->toContain('ARG PHP_VERSION=8.5')->toContain('pdo_mysql')->not->toMatch('/redis|horizon/i')
        ->and($backup)->toContain('mariadb-dump')->not->toMatch('/\bmysqldump\b/');
});

test('entrypoint bermigrasi hanya bila RUN_MIGRATIONS=true dan tidak memakai storage:link', function () use ($akar) {
    $entrypoint = (string) file_get_contents($akar.'/docker/entrypoint.sh');

    expect($entrypoint)->toContain('if [ "${RUN_MIGRATIONS:-false}" = "true" ]')
        ->toContain('php artisan optimize')
        ->toContain('php artisan filament:optimize')
        ->toContain('php artisan icons:cache')
        ->not->toMatch('/^\s*php artisan storage:link/m');
});

test('contoh .env.production memakai subpath & cookie aman', function () use ($akar) {
    $env = (string) file_get_contents($akar.'/.env.production.example');

    expect($env)->toContain('APP_ENV=production')
        ->toContain('APP_DEBUG=false')
        ->toContain('APP_URL=https://supportfkip.unsil.ac.id/keuangan')
        ->toContain('ASSET_URL=https://supportfkip.unsil.ac.id/keuangan')
        ->toContain('SESSION_PATH=/keuangan')
        ->toContain('SESSION_COOKIE=keuangan_session')
        ->toContain('SESSION_SECURE_COOKIE=true')
        ->not->toMatch('/^TRUSTED_PROXIES=\*/m')
        ->not->toMatch('/^(APP_KEY|DB_PASSWORD|REDIS_PASSWORD|MAIL_PASSWORD)=\S/m');
});

test('cadangan harian 01:30 WIB dengan retensi bawaan 30 hari dan pemulihan menolak produksi', function () use ($akar) {
    $dockerfile = (string) file_get_contents($akar.'/docker/backup/Dockerfile');
    $backup = (string) file_get_contents($akar.'/docker/backup/backup.sh');
    $restore = (string) file_get_contents($akar.'/docker/backup/restore.sh');

    expect($dockerfile)->toContain('30 1 * * *')->toContain('Asia/Jakarta')
        ->and($backup)->toContain('--single-transaction')->toContain('--routines')->toContain('--triggers')
        ->toContain('RETENSI_HARI="${RETENSI_HARI:-30}"')->toContain('sha256sum')
        ->and($restore)->toContain('"${APP_ENV:-}" = "production"')->toContain('--paksa');
});

test('auto-pull: deploy dipicu tag rilis, lolos uji penuh, dan skrip rilis menjaga cadangan, kesehatan, rollback', function () use ($akar) {
    $alur = (string) file_get_contents($akar.'/.github/workflows/deploy.yml');
    $uji = (string) file_get_contents($akar.'/.github/workflows/uji.yml');
    $skrip = (string) file_get_contents($akar.'/scripts/deploy.sh');

    expect($alur)->toContain("tags: [ 'v[0-9]+.[0-9]+.[0-9]+' ]")
        ->toContain('needs: uji')
        ->toContain('cancel-in-progress: false')
        ->toContain('git fetch --tags')
        ->toContain('bash scripts/deploy.sh')
        ->not->toContain('branches:')
        ->and($uji)->toContain('workflow_call:')->toContain('scripts/*.sh')
        ->and($skrip)->toContain('set -euo pipefail')
        ->toContain('git merge-base --is-ancestor')
        ->toContain('backup.sh')
        ->toContain('/api/health')
        ->toContain('--rollback')
        ->toContain('--no-build')
        ->not->toContain('--no-verify')
        ->and(is_executable($akar.'/scripts/deploy.sh'))->toBeTrue();
});
