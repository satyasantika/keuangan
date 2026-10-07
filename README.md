# Keuangan FKIP Unsil

Aplikasi pencatatan dan pelaporan keuangan kegiatan Fakultas Keguruan dan Ilmu Pendidikan (FKIP) Universitas Siliwangi: pagu unit, pengajuan kegiatan + RAB, persetujuan berjenjang, pencairan, SPJ dengan bukti (tautan), pengembalian sisa dana, kunci periode, serta dasbor dan laporan.

## Dokumen

Semua dokumen perancangan ada di `docs/vibecoding/`:

- `01-PRD.md` — kebutuhan produk dan aturan bisnis
- `02-ARSITEKTUR.md` — arsitektur
- `03-SKEMA-DATABASE.md` — skema basis data
- `04-PROMPT-BERTAHAP.md` — rencana kerja bertahap per fase
- `05-UJI-PENERIMAAN.md` — uji penerimaan
- `STANDAR-TEKNIS.md`, `STANDAR-GIT.md` — standar bersama
- `CLAUDE.md` — konteks untuk agen AI (juga di root repo)

Keputusan arsitektural (ADR) ada di `docs/keputusan/`.

## Prasyarat

- PHP 8.5 (dijalankan di container Docker, bukan di host), Composer 2
- Node 22
- Docker (WSL2) dan Git
- MariaDB di host, Redis bersama

## Cara menjalankan

Akan dilengkapi di F1.9 (ringkasan sementara di bawah).

### Menjalankan lokal

Infrastruktur (di luar repo ini, di `/home/satya/code`, lihat `docs/keputusan/0003-lingkungan-dan-penyimpangan-standar.md`):
container `keuangan-php`, `keuangan-nginx`, `keuangan-scheduler` (file `docker-compose.yml`), MariaDB native di host
(`db_keuangan`, `db_keuangan_testing`), dan Redis bersama (container `redis`). Tanpa Sail, tanpa PHP di host.

```bash
alias kup='docker exec keuangan-php'
cd /home/satya/code && docker compose up -d --build keuangan-php keuangan-nginx keuangan-scheduler
cd keuangan
cp .env.example .env          # isi DB_PASSWORD
kup composer install
kup php artisan key:generate
kup php artisan migrate
npm install && npm run build  # Node di host (container tidak memiliki Node)
```

Aplikasi: http://localhost:8035/keuangan/ (juga http://localhost:8035 untuk pemeriksaan langsung) · Mailpit: http://localhost:8025 (bila tersedia).

### Subpath `/keuangan`

Produksi: `https://supportfkip.unsil.ac.id/keuangan`. Reverse proxy memotong awalan sebelum meneruskan ke Nginx aplikasi,
sedangkan aplikasi membangkitkan semua URL dengan awalan itu (`URL::forceRootUrl`, `ASSET_URL`, `SESSION_PATH=/keuangan`,
`SESSION_COOKIE=keuangan_session`, `trustProxies`). Nginx lokal (`/home/satya/code/docker/nginx/keuangan.conf`) meniru
pemotongan awalan itu, sehingga lokal dan produksi berperilaku sama. Aset dibangun dengan `ASSET_URL` terisi
(`npm run build` membaca `.env`, base `/keuangan/build/`). Dilarang `href="/..."`, `src="/..."`, `action="/..."`,
`fetch('/...')` di Blade/JS (diuji `tests/Arch/SubpathTest.php`).

Blok reverse proxy produksi (di server depan):

```nginx
location = /keuangan { return 301 /keuangan/; }
location /keuangan/ {
    proxy_pass http://127.0.0.1:<port>/;          # garis miring akhir = awalan dipotong
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Prefix /keuangan;
    client_max_body_size 20m;
}
```
