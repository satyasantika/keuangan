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

Aplikasi: http://localhost:8035 · Mailpit: http://localhost:8025 (bila tersedia).
