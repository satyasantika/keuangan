# Deploy produksi — Keuangan FKIP Unsil

Pola `docker-apps` (STANDAR-TEKNIS §7): container `app` (PHP-FPM), `web` (Nginx), `queue` (Horizon),
`scheduler` (`schedule:work`), `mysql` (8.4) dan `redis` (7, AOF). Aplikasi dipasang di subpath
`https://supportfkip.unsil.ac.id/keuangan` (STANDAR-TEKNIS §2.4).

## Prasyarat server

- Docker Engine + Compose v2, akses `git` ke repositori, HTTPS valid di server depan.
- Port host `WEB_PORT` (bawaan 8035) hanya dapat diakses reverse proxy depan.

## Reverse proxy depan (server `supportfkip.unsil.ac.id`)

```nginx
location = /keuangan { return 301 /keuangan/; }
location /keuangan/ {
    proxy_pass http://127.0.0.1:8035/;          # garis miring akhir = awalan dipotong
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Prefix /keuangan;
    client_max_body_size 10m;                     # hanya impor Excel/CSV
}
```

`TRUSTED_PROXIES` di `.env.production` diisi IP proxy ini (bukan `*`), agar header `X-Forwarded-*` dipercaya.

## Deploy pertama

```bash
git clone <repo> keuangan && cd keuangan
git checkout v1.0.0
cp .env.production.example .env.production
# isi APP_KEY (docker run --rm keuangan-app php artisan key:generate --show setelah build, atau php artisan key:generate --show di mesin dev),
# DB_PASSWORD, DB_ROOT_PASSWORD, REDIS_PASSWORD, MAIL_*, TRUSTED_PROXIES, SEED_ADMIN_*
export APP_VERSION=1.0.0
docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build
docker compose --env-file .env.production -f docker-compose.prod.yml exec app php artisan db:seed --force   # peran, izin, super-admin, master contoh
curl -fsS http://127.0.0.1:8035/api/health                                                                 # {"status":"ok",...}
```

- Migrasi berjalan otomatis di container `app` (`RUN_MIGRATIONS=true`); `queue`/`scheduler` tidak bermigrasi.
- Entrypoint menjalankan `php artisan optimize`, `filament:optimize`, `icons:cache`. `storage:link` **tidak** dipakai
  (tidak ada berkas publik/unggahan; bukti berupa tautan Google Drive).
- Aset Vite dibangun di image dengan `ASSET_URL=https://supportfkip.unsil.ac.id/keuangan` (build arg).
- Volume `tmp` (ekspor/impor sementara, dibersihkan ≤ 24 jam oleh `keuangan:bersihkan-sementara`) dipakai bersama
  `app`, `queue`, `scheduler`.
- **Jangan** menjalankan `DemoSeeder` di produksi.

## Pembaruan versi

```bash
cd keuangan
git fetch --tags && git checkout vX.Y.Z
export APP_VERSION=X.Y.Z
docker compose --env-file .env.production -f docker-compose.prod.yml build
docker compose --env-file .env.production -f docker-compose.prod.yml up -d          # app menjalankan migrate --force
docker compose --env-file .env.production -f docker-compose.prod.yml exec queue php artisan horizon:terminate   # pekerja memuat kode baru
curl -fsS http://127.0.0.1:8035/api/health
```

Sebelum memperbarui, pastikan cadangan MySQL terbaru ada (lihat `docs/runbook-cadangan.md`; service `backup`
membuat dump harian 01:30 WIB ke `./backup/mysql`).

## Rollback ke tag sebelumnya

```bash
git checkout v<sebelumnya>
export APP_VERSION=<sebelumnya>
docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build
```

Bila versi baru membawa migrasi yang tidak kompatibel ke belakang, pulihkan dump MySQL sebelum pembaruan
(`docker/backup/restore.sh`, lihat runbook cadangan) — migrasi yang sudah dirilis tidak pernah diubah.

## Horizon & log

- Dasbor antrean: `https://supportfkip.unsil.ac.id/keuangan/horizon` (hanya **super-admin**, login panel dulu).
- Log aplikasi: volume `logs` (`storage/logs`, kanal `daily`, data sensitif disamarkan).
- Status layanan: `docker compose --env-file .env.production -f docker-compose.prod.yml ps` — `web` sehat bila
  `/api/health` mengembalikan `ok`; `app` sehat bila ping PHP-FPM membalas `pong`.
