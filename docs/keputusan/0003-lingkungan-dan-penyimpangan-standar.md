# 0003 — Lingkungan pengembangan dan penyimpangan dari standar paket

- Status: Diterima
- Tanggal: 2026-10-07

Keputusan pemilik tentang lingkungan dan standar teknis:

| Topik | Paket | Keputusan |
|---|---|---|
| PHP | 8.4 | **8.5** (image `php:8.5-fpm`, container `keuangan-php`) |
| Basis data | MySQL 8.4, `utf8mb4_0900_ai_ci` | **MariaDB 10.11 native** di host; `db_keuangan`, `db_keuangan_testing`; collation `utf8mb4_unicode_ci`; fitur khusus MySQL dihindari; uji berjalan di MariaDB |
| Redis | DB 32/33/34 | Redis bersama hanya punya DB 0–15; dipakai **13/14/15** (default/cache/queue), prefiks `keuangan_` |
| Compose/Nginx | `docker-compose.yml` di repo | Berada di `/home/satya/code` (`docker-compose.yml`, `docker-compose.prod.yml`, `docker/nginx/keuangan.conf`, `php/laravel8.5.Dockerfile`); port Nginx 8035; jaringan `code_laranet` |
| Domain | `supportfkip.unsil.ac.id/keuangan` | Tetap; lokal langsung `http://localhost:8035` |
| Peran `super-admin` | Tampil | Disembunyikan dari landing page & panduan publik |
| Hook `pre-commit` | `docker compose exec` dari repo | Disesuaikan memakai `docker exec keuangan-php` (compose tidak di repo) |
| CI | `ci.yml` MySQL 8.4 | `uji.yml` (MariaDB 10.11, PHP 8.5) + `deploy.yml` (SSH, `~/docker-apps/deploy.sh keuangan`) mengikuti pola `sigap` |
| Git | Remote + PR | Tanpa remote: branch fase, `merge --no-ff` ke `main`, tag lokal |
| Tipe kolom UUID | `CHAR(36)` (MySQL) | Di MariaDB 10.11 `$table->uuid()` / `foreignUuid()` / `uuidMorphs()` otomatis menjadi tipe bawaan `UUID` (16 byte, dibaca/ditulis sebagai string 36 karakter). Dipakai apa adanya; antarmuka PHP identik. Uji `UuidTest` menerima `uuid` atau `char(36)` |
