# Paket Vibecoding — Sistem Keuangan Fakultas FKIP Unsil

Paket ini berisi bahan untuk membangun aplikasi **Keuangan FKIP Unsil** (Laravel 13 + Filament 5 + MySQL 8.4 + Redis 7) bersama agen AI (Claude Code atau sejenisnya), langkah demi langkah, dengan satu commit git standar di setiap langkah.

Aplikasi mencakup: pagu per unit/prodi per tahun, pengajuan kegiatan dengan RAB rinci, persetujuan berjenjang, revisi RAB bernomor, pencairan (UP/TUP/LS — istilah perlu verifikasi), SPJ/realisasi per item dengan **tautan** bukti (Google Drive, akses terbatas unsil.ac.id; tanpa unggah ke server), pembekuan tautan saat SPJ diterima, pencatatan potongan pajak sebagai informasi, pengembalian sisa dana, batas waktu SPJ dengan pengingat, kunci periode, dasbor anggaran vs realisasi, laporan periodik, dan jejak audit.

> **Kebijakan berkas:** sistem **tidak menerima unggahan berkas** karena storage server terbatas (STANDAR-TEKNIS §1a). Semua lampiran dan bukti berupa tautan di tabel `tautan_berkas`; keluaran PDF/Excel di-*stream* atau disimpan sementara ≤ 24 jam; hanya impor Excel/CSV yang diterima lalu dihapus setelah diproses. Unit disarankan memakai Shared Drive fakultas/unit.

> **Penting sebelum membangun:** Fase F0 memuat langkah non-koding **"Klarifikasi batas cakupan"** (`docs/keputusan/0001-batas-cakupan.md`) karena honor ujian sudah ditangani `fkipapp` dan portal Support System FKIP sudah menautkan aplikasi "Laporan Keuangan" (`/laporankeuangan/`). Jangan lanjut ke F1 sebelum keputusan itu diambil.

## Isi paket

| Berkas | Isi | Siapa yang membaca |
|---|---|---|
| `README.md` | Peta bahan ini | Semua |
| `01-PRD.md` | Kebutuhan produk: latar belakang, dasar regulasi, peran & matriks izin, modul, user story, aturan bisnis BR-01…BR-26, diagram status, non-fungsional, laporan, di luar cakupan | Pemilik produk, Admin Keuangan, pengembang |
| `02-ARSITEKTUR.md` | Stack, paket, pemakaian Redis (lock, cache, antrean, rate limit), struktur folder, panel & Resource Filament, job/penjadwal, notifikasi, penyimpanan berkas, diagram komponen | Pengembang |
| `03-SKEMA-DATABASE.md` | ERD, tabel per tabel, Enum status, seeder awal, indeks | Pengembang |
| `04-PROMPT-BERTAHAP.md` | **Inti**: 13 fase (F0–F12), 73 langkah, tiap langkah berisi prompt siap tempel, kriteria selesai, dan perintah commit | Pengembang (dosen/staf) yang menjalankan agen |
| `05-UJI-PENERIMAAN.md` | Skenario UAT per peran, daftar test Pest minimal, checklist keamanan, checklist go-live | Admin Keuangan, penguji, TI |
| `CLAUDE.md` | Konteks ringkas untuk agen AI — disalin ke root repo aplikasi | Agen AI |
| `STANDAR-TEKNIS.md` | Standar teknis bersama Support System FKIP (salinan identik) | Semua pengembang |
| `STANDAR-GIT.md` | Standar git & commit bersama (salinan identik) | Semua pengembang |

## Urutan pakai

1. **Baca berurutan** `01-PRD.md` → `02-ARSITEKTUR.md` → `03-SKEMA-DATABASE.md` → `04-PROMPT-BERTAHAP.md` → `05-UJI-PENERIMAAN.md`. Untuk pimpinan/Admin Keuangan cukup `01-PRD.md` dan `05-UJI-PENERIMAAN.md`.
2. **Kerjakan F0** (persiapan repositori dan dua dokumen keputusan non-koding). Pada F0.1 seluruh paket disalin ke `docs/vibecoding/` dan **`CLAUDE.md` disalin ke root repo**.
3. **Jalankan `04-PROMPT-BERTAHAP.md` langkah demi langkah**: tempel prompt ke agen → tinjau hasil dan `git diff` → jalankan gerbang kualitas (`kup ./vendor/bin/pint && php artisan test`) → commit dengan pesan yang tertulis di langkah itu. Satu langkah = satu sesi agen = satu commit.
4. Di akhir setiap fase jalankan langkah **"Tutup fase"**: push branch, buka PR, merge setelah CI hijau, beri tag versi.
5. Setelah F12.5, jalankan UAT memakai `05-UJI-PENERIMAAN.md`, lalu rilis `v1.0.0` (F12.7).

## Fase dan tag versi

| Fase | Judul | Branch | Langkah | Tag |
|---|---|---|---|---|
| F0 | Persiapan repositori & klarifikasi cakupan (termasuk ADR 0001 batas cakupan, ADR 0002 parameter) | `main` | 4 | — |
| F1 | Fondasi Laravel (Docker FKIP, Redis, Horizon, Pest, Larastan, Pint, Boost, health, CI) | `feat/f1-fondasi` | 9 | `v0.1.0` |
| F2 | Autentikasi, peran & audit (Filament 5, permission, activitylog, riwayat status, helper uang/lock/penomoran, MFA) | `feat/f2-auth-audit` | 7 | `v0.2.0` |
| F3 | Master data & pagu (tahun anggaran & periode, unit, akun BAS, pajak, pengaturan, pagu) | `feat/f3-master-pagu` | 6 | `v0.3.0` |
| F4 | Pengajuan kegiatan & RAB (draf, form RAB, tautan berkas + pemeriksaan & pratinjau, ajukan bernomor, PDF + QR) | `feat/f4-pengajuan-rab` | 8 | `v0.4.0` |
| F5 | Persetujuan berjenjang | `feat/f5-persetujuan` | 4 | `v0.5.0` |
| F6 | Pencairan | `feat/f6-pencairan` | 3 | `v0.6.0` |
| F7 | SPJ, realisasi, pajak, pernyataan berkas final & pembekuan tautan, pengembalian | `feat/f7-spj` | 8 | `v0.7.0` |
| F8 | Revisi RAB | `feat/f8-revisi-rab` | 4 | `v0.8.0` |
| F9 | Kunci periode | `feat/f9-kunci-periode` | 3 | `v0.9.0` |
| F10 | Notifikasi & penjadwal | `feat/f10-notifikasi` | 4 | `v0.10.0` |
| F11 | Laporan, dasbor & ekspor (termasuk impor rekap honor fkipapp opsional) | `feat/f11-laporan` | 6 | `v0.11.0` |
| F12 | Pengerasan & rilis (uji otorisasi, keamanan, Docker produksi, cadangan, UAT) | `feat/f12-rilis` | 7 | `v1.0.0` |
| | **Total** | | **70** | |

## Prasyarat

| Perangkat | Versi | Catatan |
|---|---|---|
| Windows + **WSL2** (Ubuntu 24.04) | — | Kerjakan proyek di sistem berkas WSL (`~/code/<app>`), bukan `/mnt/c` |
| **Docker FKIP** (WSL2) | sudah ada | Container `keuangan-*`; MySQL 8.4 native di host; Redis & Mailpit bersama (STANDAR-TEKNIS §2) |
| **PHP** | 8.4 (min. 8.3) — di dalam image `keuangan-php`, tidak perlu di host | Ekstensi dipasang di Dockerfile (STANDAR-TEKNIS §2.3) |
| **Composer** | 2.x — di dalam image `keuangan-php`, tidak perlu di host | |
| **Node.js** | 22 LTS — di dalam image `keuangan-php`, tidak perlu di host | Untuk Vite/Tailwind 4 |
| **Git** | 2.40+ | Identitas `user.name`/`user.email` diatur |
| Agen AI | Claude Code (disarankan) | Laravel Boost dipasang di F1.6 |
| Akun remote | GitHub/GitLab kampus | Untuk PR, CI, tag |

Catatan Docker: aplikasi berjalan di container `keuangan-*` (Docker FKIP, STANDAR-TEKNIS §2); MySQL native di host (`host.docker.internal`), Redis & Mailpit bersama. Perintah PHP lewat alias `kup` = `docker compose exec keuangan-php`; hook git memanggil container secara otomatis.

## Hal yang ditandai "perlu verifikasi"

Paket ini sengaja tidak mengarang fakta. Butir berikut harus dikonfirmasi bagian keuangan/hukum (dicatat di ADR 0002 pada F0.3) dan dibuat dapat diubah lewat master/pengaturan:

- Status kelembagaan Unsil (PTN Satker atau PTN-BLU) dan status terkini PP 23/2005 jo. PP 74/2012.
- Nomor pasal kunci UU 17/2003 dan UU 1/2004 yang dirujuk; nomor PMK Bagan Akun Standar dan PMK tata cara pembayaran (UP/TUP/LS) terbaru.
- Istilah dan penerapan jenis pencairan UP/TUP/LS di tingkat fakultas.
- Struktur penyetuju: keberadaan BPP di fakultas, penyetuju akhir (Wakil Dekan bidang keuangan atau PPK), tahap verifikasi Kaprodi/Kepala Unit.
- Kode & nama akun contoh di seeder; tarif pajak referensi (PPh 21/22/23, PPh 4(2), PPN) dan dasar hukumnya.
- Batas hari SPJ (usulan 14 hari kalender) dan kebijakan tanggal bukti sebelum kegiatan.
- Format tabel penggunaan dana untuk akreditasi (LKPS/LAMDIK) dan kategori penggunaan dana.
- Definisi semester pada laporan (anggaran vs akademik); retensi arsip keuangan; kebutuhan NPWP/NIK penerima.
- Relevansi indikator kinerja pelaksanaan anggaran (mis. IKPA) di tingkat fakultas.
- Penerimaan bukti SPJ berupa tautan Google Drive (bukan berkas fisik/unggahan) oleh pemeriksa/auditor, dan apakah tetap diperlukan arsip cetak/PDF terpisah.
