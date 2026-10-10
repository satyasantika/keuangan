# Runbook cadangan & pemulihan — Keuangan FKIP Unsil

Server **tidak menyimpan berkas pengguna** (STANDAR-TEKNIS §1a): yang dicadangkan hanya basis data MySQL.
Bukti keuangan berupa tautan Google Drive milik unit.

## Jadwal, lokasi, retensi

| Hal | Ketentuan |
|---|---|
| Jadwal | Harian pukul **01:30 WIB** (service `backup` di `docker-compose.prod.yml`) |
| Isi | `mysqldump --single-transaction --routines --triggers` basis data `keuangan`, dikompresi gzip |
| Lokasi | Volume host `./backup/mysql/keuangan-{Y-m-d-His}.sql.gz` + berkas `.sha256` |
| Retensi | `RETENSI_HARI` (bawaan **30 hari**); berkas lebih lama dihapus otomatis |
| Salinan luar server | Salin harian `./backup/mysql` ke penyimpanan TI fakultas (rsync/NAS) — tanggung jawab TI |
| Penanggung jawab | Admin TI Fakultas (operasional), Admin Keuangan (mengetahui hasil uji pulih) |

Skrip: `docker/backup/backup.sh` (keluar non-zero bila gagal; log di `docker compose logs backup`).
Menjalankan cadangan manual:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml exec backup /usr/local/bin/backup.sh
```

## Pemulihan ke lingkungan uji

`docker/backup/restore.sh <berkas.sql.gz>` memeriksa checksum sha256, menguji gzip, lalu memulihkan ke
`DB_DATABASE` pada `DB_HOST`. Skrip **menolak berjalan bila `APP_ENV=production`** kecuali diberi `--paksa`
(hanya untuk pemulihan bencana, atas persetujuan Wadek bidang keuangan).

```bash
# di server uji (APP_ENV=staging)
DB_HOST=127.0.0.1 DB_DATABASE=keuangan_uji DB_USERNAME=... DB_PASSWORD=... \
  docker/backup/restore.sh backup/mysql/keuangan-2027-01-31-013000.sql.gz
php artisan keuangan:rekonsiliasi      # total denormalisasi harus tanpa selisih
```

## Uji pulih per semester

Dilakukan tiap Januari dan Juli (05-UJI-PENERIMAAN G-09).

- [ ] Pilih dump terbaru dan satu dump ≥ 7 hari lalu; periksa `sha256sum -c`.
- [ ] Pulihkan ke server uji dengan `restore.sh`.
- [ ] `php artisan migrate:status` — tidak ada migrasi tertunda untuk versi yang sama.
- [ ] `php artisan keuangan:rekonsiliasi` tanpa selisih.
- [ ] Login uji per peran; buka 3 pengajuan acak, SPJ diterima beserta tautan bekunya.
- [ ] Bandingkan LAP-01 tahunan di server uji dengan produksi pada tanggal dump.
- [ ] Catat hasil di tabel log berikut.

| Tanggal | Berkas dump | Pelaksana | Durasi pulih | Hasil rekonsiliasi | Catatan |
|---|---|---|---|---|---|
| | | | | | |

## Berkas bukti di Google Drive

- Simpan bukti di **Shared Drive fakultas/unit**, bukan Drive pribadi, agar berkas tidak hilang saat pegawai
  pindah atau purnatugas.
- Bila bukti terlanjur di Drive pribadi: **pindahkan kepemilikan** ke akun unit/Shared Drive sebelum pegawai
  pindah/purnatugas; tautan Drive tetap sama bila berkas dipindah ke Shared Drive.
- `tautan_berkas_beku` (bagian dari dump MySQL) adalah **jejak audit URL + waktu** saat SPJ/pengembalian diterima;
  isi berkas tidak ikut dicadangkan oleh sistem ini.

## Data aplikasi lama `laporankeuangan`

Sesuai ADR 0001 (opsi b), data lama **tetap berada di `/laporankeuangan/`** dan tidak dimigrasikan; aplikasi
ini melengkapinya. Cadangan aplikasi lama mengikuti prosedurnya sendiri.
