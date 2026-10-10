<?php

/**
 * Teks halaman eror HTTP. Kunci = kode status; 'ulang' menampilkan tombol "Coba lagi" (hanya GET).
 */
return [
    '400' => ['judul' => 'Permintaan tidak dapat diproses', 'pesan' => 'Permintaan yang dikirim tidak lengkap atau formatnya tidak sesuai.', 'saran' => ['Muat ulang halaman lalu ulangi langkah Anda.', 'Bila berulang, hapus data situs (cookie) untuk aplikasi ini.']],
    '401' => ['judul' => 'Anda belum masuk', 'pesan' => 'Halaman ini hanya dapat dibuka setelah Anda masuk ke aplikasi.', 'saran' => ['Masuk dengan akun Anda, lalu buka kembali halaman ini.']],
    '403' => ['judul' => 'Akses tidak diizinkan', 'pesan' => 'Peran Anda tidak memiliki izin untuk membuka halaman atau melakukan tindakan ini.', 'saran' => ['Pastikan Anda masuk dengan akun yang benar.', 'Pengguna unit hanya dapat mengakses data unitnya sendiri.', 'Hubungi admin bila Anda merasa seharusnya punya akses.']],
    '404' => ['judul' => 'Halaman tidak ditemukan', 'pesan' => 'Alamat yang Anda buka tidak ada atau datanya sudah tidak tersedia.', 'saran' => ['Periksa kembali penulisan alamat.', 'Gunakan menu di aplikasi alih-alih alamat yang tersimpan lama.']],
    '405' => ['judul' => 'Metode tidak diizinkan', 'pesan' => 'Alamat ini tidak menerima jenis permintaan yang dikirim.', 'saran' => ['Buka halaman lewat tautan atau menu di aplikasi.']],
    '408' => ['judul' => 'Waktu permintaan habis', 'pesan' => 'Server menunggu terlalu lama sebelum permintaan selesai dikirim.', 'saran' => ['Periksa koneksi internet Anda, lalu coba lagi.'], 'ulang' => true],
    '409' => ['judul' => 'Data sedang diproses', 'pesan' => 'Data ini baru saja diubah atau sedang diproses pengguna lain.', 'saran' => ['Tunggu beberapa saat, muat ulang halaman, lalu ulangi.'], 'ulang' => true],
    '410' => ['judul' => 'Halaman sudah tidak tersedia', 'pesan' => 'Halaman ini telah dihapus dan tidak akan tersedia lagi.', 'saran' => ['Gunakan menu di aplikasi untuk menemukan data terbaru.']],
    '413' => ['judul' => 'Berkas terlalu besar', 'pesan' => 'Ukuran data yang dikirim melebihi batas. Impor dibatasi maksimal 10 MB.', 'saran' => ['Pecah berkas impor menjadi beberapa bagian yang lebih kecil.']],
    '414' => ['judul' => 'Alamat terlalu panjang', 'pesan' => 'Alamat yang dibuka melebihi panjang yang diizinkan.', 'saran' => ['Kurangi filter atau pencarian, lalu coba lagi.']],
    '415' => ['judul' => 'Jenis berkas tidak didukung', 'pesan' => 'Format data yang dikirim tidak dapat diproses.', 'saran' => ['Gunakan berkas Excel (.xlsx) atau CSV untuk impor.']],
    '419' => ['judul' => 'Sesi telah berakhir', 'pesan' => 'Halaman kedaluwarsa karena terlalu lama dibiarkan atau Anda keluar dari perangkat lain.', 'saran' => ['Muat ulang halaman, masuk kembali bila diminta, lalu ulangi langkah Anda.', 'Perubahan yang belum disimpan perlu dimasukkan ulang.']],
    '422' => ['judul' => 'Data tidak dapat diproses', 'pesan' => 'Data yang dikirim tidak memenuhi aturan yang berlaku.', 'saran' => ['Periksa isian Anda, lalu kirim ulang.']],
    '423' => ['judul' => 'Data sedang terkunci', 'pesan' => 'Data ini terkunci, misalnya karena periode sudah dikunci atau tahun anggaran ditutup.', 'saran' => ['Hubungi admin keuangan bila perubahan masih diperlukan.']],
    '429' => ['judul' => 'Terlalu banyak permintaan', 'pesan' => 'Anda mengirim terlalu banyak permintaan dalam waktu singkat.', 'saran' => ['Tunggu satu menit lalu coba lagi.'], 'ulang' => true],
    '500' => ['judul' => 'Terjadi kesalahan pada server', 'pesan' => 'Maaf, ada masalah di sisi kami. Tim pengelola sudah dapat menelusurinya dari catatan sistem.', 'saran' => ['Coba muat ulang beberapa saat lagi.', 'Bila berulang, sampaikan waktu kejadian di bawah ini kepada admin.'], 'ulang' => true],
    '501' => ['judul' => 'Fitur belum tersedia', 'pesan' => 'Server belum mendukung permintaan ini.', 'saran' => ['Hubungi admin bila Anda memerlukannya.']],
    '502' => ['judul' => 'Layanan tidak merespons', 'pesan' => 'Server aplikasi sedang tidak dapat dijangkau, mungkin sedang dimulai ulang.', 'saran' => ['Tunggu satu sampai dua menit, lalu muat ulang.'], 'ulang' => true],
    '503' => ['judul' => 'Layanan sedang dalam pemeliharaan', 'pesan' => 'Aplikasi sedang dalam pemeliharaan atau pembaruan. Data Anda aman.', 'saran' => ['Coba lagi beberapa saat lagi.'], 'ulang' => true],
    '504' => ['judul' => 'Server terlalu lama menjawab', 'pesan' => 'Permintaan memerlukan waktu lebih lama dari batas yang diizinkan.', 'saran' => ['Muat ulang. Untuk laporan besar, persempit rentang periode atau gunakan ekspor yang diantrekan.'], 'ulang' => true],
    'umum' => ['judul' => 'Terjadi kesalahan', 'pesan' => 'Permintaan tidak dapat diselesaikan.', 'saran' => ['Muat ulang halaman atau kembali ke beranda.']],
];
