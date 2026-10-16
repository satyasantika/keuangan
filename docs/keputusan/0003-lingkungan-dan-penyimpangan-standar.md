# 0003 — Lingkungan pengembangan dan penyimpangan dari standar paket

- Status: Diterima
- Tanggal: 2026-10-07
- Catatan: Disesuaikan oleh keputusan 15 Oktober 2026 (Profil Ringan): baris **Redis** di bawah (DB 13/14/15) tidak berlaku lagi setelah Fase B; cache, sesi, antrean, dan lock memakai driver `database`, dan Redis/Horizon/Octane tidak dipakai (STANDAR-TEKNIS §1b). Isi asli dipertahankan sebagai catatan historis.
- Catatan: Lanjutan keputusan 16 Oktober 2026: nama database seragam `db_<app>`/`db_<app>_testing` (`db_keuangan`, `db_keuangan_testing`; nama pengguna DB tidak diubah); tipe UUID mengikuti Blueprint Laravel 13 (UUID native MariaDB; tidak ada klaim `CHAR(36)` sebagai tipe kolom, `char('id', 36)`/`binary(16)` eksplisit dilarang); produksi memakai MariaDB native di host lewat `host.docker.internal` (`extra_hosts` host-gateway) tanpa container database di compose produksi (service `mysql` dihapus pada Fase B; CI boleh service container sementara); alat dump `mariadb-dump`; `composer.json` `^8.3` dinaikkan ke `^8.5` pada Fase B. Isi tabel di bawah tidak diubah.
- Catatan: Keputusan pemilik 16 Oktober 2026 menetapkan untuk semua sistem: PHP 8.5, MariaDB 10.11 LTS atau lebih baru (native di host, bukan container), SQLite tidak dipakai, kolasi default `utf8mb4_unicode_ci` (STANDAR-TEKNIS diperbarui). Baris PHP dan basis data di bawah sejalan dengan keputusan itu dan tidak diubah; yang berubah adalah standar paketnya, sehingga kolom "Paket" untuk kedua baris itu kini menjadi catatan historis. Dokumen spesifikasi `_vibecoding/docs/*` dan `README.md` sudah diselaraskan; `docker-compose.prod.yml` (service `mysql:8.4`) dan skrip cadangan diselaraskan pada Fase B.

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
