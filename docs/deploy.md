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

## Auto-pull dari GitHub (CD)

Pola mengikuti `sigap`/`puspresma`/`alias` (GitHub Actions → SSH ke server) dengan skrip rilis yang ikut di repo
seperti `silogy` (`scripts/deploy.sh`), bukan salinan di `~/docker-apps/deploy.sh`.

Alur: `git tag -a vX.Y.Z -m "..." && git push origin vX.Y.Z` → job `uji` (pint, phpstan, pest, shellcheck; dipakai ulang
dari `uji.yml`) → job `deploy` masuk ke server dan menjalankan:

```bash
cd ~/docker-apps/keuangan
git fetch --tags --prune --force origin && git fetch origin main
git checkout --detach "refs/tags/$TAG"
bash scripts/deploy.sh
```

`scripts/deploy.sh`: menolak bila HEAD bukan tag `vX.Y.Z`, tag tidak ada di riwayat `origin/main`, atau working tree
kotor → cadangan MySQL pra-migrasi (service `backup`) → build → `up -d` (migrasi di `app`) → health check
`/api/health` (maks. 2 menit; gagal = job merah + 50 baris log `app`) → `horizon:terminate` → hapus image lama
(3 versi terakhir disimpan). Push biasa ke `main` **tidak** menggelar apa pun; hanya tag yang dirilis.

- Rahasia repositori (diisi manusia): `SERVER_HOST`, `SERVER_USER`, `SSH_PRIVATE_KEY` (kunci khusus deploy; publiknya di `authorized_keys`).
- Server: clone repo ke `~/docker-apps/keuangan` (perlu verifikasi dengan UPT TIK), `.env.production` di sana, akun deploy boleh menjalankan `docker`.
  Pakai *deploy key* GitHub baca-saja bila repo privat agar `git fetch` jalan tanpa kata sandi.
- Deploy ulang/rollback dari GitHub: *Actions → Deploy keuangan → Run workflow* dengan tag tujuan. Rollback cepat di server
  tanpa build ulang: `bash scripts/deploy.sh --rollback X.Y.Z` (`--list` menampilkan versi tersedia). Rollback hanya
  mengembalikan kode, bukan skema — lihat bagian Rollback.
- Aktif setelah repo punya remote GitHub (ADR 0003: saat ini belum ada remote; tidak ada push dilakukan).

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

## Checklist go-live (tugas manusia)

Butir 05-UJI-PENERIMAAN §4 yang tidak dapat diverifikasi dari kode dan harus dikerjakan/ditandatangani:

1. **G-01/G-02** — Sahkan ADR 0001 (nama, tanggal, paraf) dan konfirmasi parameter ADR 0002; lencana *Perlu
   verifikasi* di **Sistem → Pengaturan** menunjukkan butir yang masih terbuka.
2. **G-03** — Muat master resmi: unit FKIP, impor akun BAS resmi, jenis pencairan (hapus tanda perlu verifikasi),
   tarif pajak referensi beserta dasar hukum (diisi bagian keuangan), kategori penggunaan dana.
3. **G-04/G-05** — Buat pengguna per peran (unit benar, MFA aktif), tahun anggaran berjalan, dan tetapkan pagu semua unit.
4. **G-06** — Server: sertifikat HTTPS valid, blok reverse proxy `/keuangan/`, `.env.production` (`APP_ENV=production`,
   `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `TRUSTED_PROXIES` = IP proxy).
5. **G-07** — `docker compose … up -d --build` di server; cek `/api/health` = ok, Horizon & scheduler berjalan.
6. **G-08** — Uji SMTP kampus (surel uji diterima); putuskan WhatsApp aktif/nonaktif.
7. **G-09** — Pastikan cadangan harian tercipta di `./backup/mysql`, salin ke luar server, uji pulih pertama dan catat di runbook.
8. **G-09a** — Shared Drive per unit dengan berbagi default *Terbatas/Universitas Siliwangi*; sosialisasi panduan berbagi.
9. **G-10/G-11** — Jalankan UAT (`docs/uat/hasil-uat.md`, data `UatSeeder` di staging), tanda tangani berita acara,
   pelatihan per peran memakai panduan `/keuangan/panduan/`.
10. **G-12** — Tambah kartu/tautan portal Support System FKIP ke `keuangan/` dan `keuangan/panduan/` (ADR 0001 opsi b:
    tautan baru berlabel jelas, `/laporankeuangan/` tetap).
11. **G-14** — Jadwalkan pendampingan bulan pertama dan jalur pelaporan isu.
12. **G-15** — Dorong tag `v1.0.0` ke remote kampus dan bangun image produksi dari tag tersebut (job CI `docker`).
