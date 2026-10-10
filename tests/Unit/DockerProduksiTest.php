<?php

/**
 * Berkas Docker produksi (STANDAR-TEKNIS §7; 05-UJI-PENERIMAAN K-19).
 */
$akar = dirname(__DIR__, 2);

test('.dockerignore mengecualikan rahasia, dump, dan berkas sementara', function () use ($akar) {
    $baris = array_map('trim', file($akar.'/.dockerignore'));

    expect($baris)->toContain('.env', '.env.*', 'storage/app/tmp/*', 'docs/referensi/rahasia', '*.sql.gz', 'vendor', 'node_modules', 'tests')
        ->not->toContain('!.env');
});

test('compose produksi memuat app, web, queue (horizon), scheduler, mysql 8.4, dan redis 7', function () use ($akar) {
    $compose = (string) file_get_contents($akar.'/docker-compose.prod.yml');

    foreach (['app:', 'web:', 'queue:', 'scheduler:', 'mysql:', 'redis:', 'backup:'] as $layanan) {
        expect($compose)->toContain("\n  {$layanan}");
    }

    expect($compose)->toContain('"php", "artisan", "horizon"')
        ->toContain('"php", "artisan", "schedule:work"')
        ->toContain('image: mysql:8.4')
        ->toContain('image: redis:7-alpine')
        ->toContain('--appendonly", "yes"')
        ->toContain('/api/health')
        ->toContain('env_file: .env.production');
});

test('entrypoint bermigrasi hanya bila RUN_MIGRATIONS=true dan tidak memakai storage:link', function () use ($akar) {
    $entrypoint = (string) file_get_contents($akar.'/docker/entrypoint.sh');

    expect($entrypoint)->toContain('if [ "${RUN_MIGRATIONS:-false}" = "true" ]')
        ->toContain('php artisan optimize')
        ->toContain('php artisan filament:optimize')
        ->toContain('php artisan icons:cache')
        ->not->toMatch('/^\s*php artisan storage:link/m');
});

test('contoh .env.production memakai subpath & cookie aman sesuai STANDAR-TEKNIS §2.4', function () use ($akar) {
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
