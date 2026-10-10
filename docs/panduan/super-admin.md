# Panduan Admin TI (`super-admin`)

Tugas: mengelola pengguna & peran, memantau antrean, dan pemeliharaan sistem. Wajib MFA. Super-admin tidak
menyetujui transaksi keuangan.

## 1. Pengguna
1. **Sistem → Pengguna → Buat**: nama, surel, NIP, no. HP, peran, dan **unit** (wajib untuk kepala-unit/admin-unit).
2. Nonaktifkan pengguna yang pindah/purnatugas (tidak dihapus). Reset MFA bila pengguna kehilangan perangkat.

## 2. Antrean & jadwal
- Horizon: `https://supportfkip.unsil.ac.id/keuangan/horizon` (antrean `notifikasi`, `ekspor`, `impor`, `default`).
- Jadwal harian: pengingat SPJ, rekonsiliasi (`keuangan:rekonsiliasi`), periksa ulang tautan, bersihkan berkas
  sementara. Selisih rekonsiliasi dikirim sebagai notifikasi ke super-admin.

## 3. Pemeliharaan
- Deploy & pembaruan versi: `docs/deploy.md`.
- Cadangan & uji pulih: `docs/runbook-cadangan.md`.
- Jejak audit: tab **Log perubahan** dan **Riwayat status** pada setiap pengajuan.
