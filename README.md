# mod_unifair — v3.3.6 (2026-08-07)

## Sorting tabel universitas v3.3.6

- Judul kolom Session, Nama, Kuota, Terpakai, Sisa, dan Urutan dapat diklik.
- Klik pertama mengurutkan naik; klik berikutnya membalik menjadi turun.
- Urutan Session mengikuti pengelompokan hari dan urutan sesi yang sama dengan
  Student View.
- Sorting tidak mengubah data, kuota, pilihan siswa, atau urutan permanen.

## Drag-and-drop urutan sesi v3.3.5

- Admin atau manager yang memiliki izin `mod/unifair:manageuni` dapat menggeser
  sesi pada halaman **Kelola Sesi**.
- Urutan disimpan otomatis dan langsung digunakan pada Student View.
- Sesi hanya dapat digeser di dalam hari/deskripsi yang sama agar jadwal
  acara multi-hari tidak tercampur.
- Mengubah urutan tidak mengubah pilihan siswa, kuota, atau data kehadiran.

## Urutan sesi multi-hari dan pembatalan waktu pelaksanaan v3.3.4

- Student View dan seluruh halaman pengelolaan mengelompokkan sesi per hari/deskripsi.
- Setiap hari menampilkan urutan sesi 1, 2, 3, 4, bukan 1, 1, 2, 2, 3, 3, 4, 4.
- Penambahan `session_start`/`session_end` dari v3.3.3 dibatalkan.
- `timeopen`/`timeclose` tetap mengatur jendela waktu siswa boleh memilih.

## Perbaikan impor dan pembersihan v3.3.2

- Activity kosong tidak lagi membuat **Session 1** secara otomatis.
- Sesi impor dibedakan menggunakan nama, deskripsi/tanggal, urutan, serta
  jadwal buka-tutup sehingga hari 28 dan 29 tidak bergabung.
- **Hapus Semua Universitas** juga membersihkan seluruh sesi kosong, pilihan,
  dan kehadiran terkait. Deskripsi activity tetap dipertahankan.

## Pengelolaan universitas v3.3.1

- Checkbox pada setiap universitas untuk menghapus beberapa item sekaligus.
- Checkbox **Pilih semua** dan tombol **Hapus yang Dipilih**.
- Penghapusan terpilih memakai konfirmasi serta membersihkan pilihan dan
  kehadiran terkait tanpa memengaruhi universitas lain.
- Tombol **Hapus Semua Universitas** tetap tersedia.

## Penguatan untuk penggunaan banyak siswa (v3.2.0)

- Capability tulis dipisahkan: melihat laporan tidak lagi otomatis memberi
  izin mengubah pilihan atau kehadiran.
- Laporan, ekspor, edit pilihan, dan kehadiran mengikuti Separate Groups.
- Perubahan pilihan, kehadiran, sesi, universitas, dan impor masuk ke Moodle
  Events/logs.
- Operasi kuota, perubahan universitas, impor, dan kehadiran dilindungi lock
  serta transaksi untuk mencegah tabrakan request.
- Impor XLSX dibatasi 5 MB, 5.000 baris, 20 kolom, dan 25 MB ukuran hasil
  ekstraksi untuk mengurangi risiko file berbahaya atau kehabisan memori.
- Restore menghitung ulang kuota dari pilihan yang benar-benar dipulihkan.
- Privacy API mencakup pengguna yang mengubah kehadiran (`modifiedby`).
- Indeks instalasi duplikat dihapus dan ekspor tidak lagi membawa sesskey.
- PHPUnit mencakup perlindungan pilihan lama ketika kuota tujuan penuh dan
  validasi satu pilihan per sesi.

## Enam fitur baru v3.1.0

- Waktu buka dan tutup untuk setiap sesi.
- Tampilan sisa kuota bagi siswa dan guru.
- Guru dapat mengubah pilihan siswa dengan pemeriksaan kuota yang sama.
- Dashboard menampilkan siswa yang belum memilih lengkap.
- Kehadiran per sesi: Hadir, Tidak Hadir, Izin, atau Belum Ditandai.
- Impor sesi dan universitas melalui CSV/XLSX; template CSV tersedia di halaman impor.

## Fitur Session

- CRUD sesi tersedia di halaman **Kelola Sesi**.
- Setiap universitas wajib berada dalam satu sesi.
- Siswa wajib memilih tepat satu universitas pada setiap sesi.
- Kuota universitas tetap berlaku dan pilihan tidak akan berubah sebagian jika
  salah satu universitas yang baru dipilih sudah penuh.
- Data instalasi lama dipertahankan. Saat upgrade, satu sesi default dibuat
  otomatis dan seluruh universitas lama dimasukkan ke sesi tersebut.
- Laporan, ekspor, Privacy API, serta backup/restore sudah menyertakan sesi.

Perbaikan dari versi awal yang di-upload. Ringkasan perubahan di bawah.
Jika plugin versi lama **sudah ter-install** di server produksi Anda, cukup
timpa folder `mod/unifair` dengan isi zip ini lalu jalankan
**Site administration → Notifications** — `db/upgrade.php` akan memigrasikan
skema dan data lama secara otomatis (tidak perlu uninstall/reinstall).

## Bug kritis yang diperbaiki

1. **Kuota "tanpa batas" (0) sebelumnya langsung dianggap penuh.**
   Sekarang `capacity = 0` konsisten berarti *unlimited* di seluruh kode
   (`view.php`, `report.php`, `manage.php`). Field `use_quota` yang dulu ada
   di database tapi tidak pernah dipakai, sudah dihapus lewat migrasi.

2. **Race condition pada penghitungan kuota.**
   Pola lama "hitung dulu, baru insert" (`COUNT(*)` lalu `INSERT`) tidak aman
   untuk submission bersamaan (mis. ratusan siswa submit begitu form dibuka).
   Sekarang pakai kolom `quotaused` yang direservasi lewat **UPDATE atomik**
   (`locallib.php::unifair_try_reserve_slot()`), dibungkus transaksi
   ber-delegasi (`start_delegated_transaction`), sehingga dua request
   bersamaan tidak bisa sama-sama lolos dan overbooking kuota.

3. **Kehilangan data saat submit sebagian gagal.**
   Sebelumnya: jika satu pilihan kuotanya penuh, seluruh pilihan lama siswa
   sudah terlanjur dihapus lebih dulu → hasil akhirnya siswa kehilangan
   semua pilihan. Sekarang `unifair_apply_choices()` menghitung selisih
   (tambah/hapus) dan hanya mengubah yang benar-benar berubah; pilihan yang
   gagal karena kuota penuh tidak memengaruhi pilihan lain yang berhasil.

4. **`moodle_exception` dipakai salah** (teks bebas dipakai sebagai kode
   error, bukan identifier lang string) → pesan error jadi rusak/generik.
   Sekarang semua pesan pakai `get_string()` dengan identifier yang benar
   (lihat `lang/en/unifair.php` dan `lang/id/unifair.php`).

5. **`FEATURE_BACKUP_MOODLE2` diklaim `true` tapi class backup/restore tidak
   ada** → course backup/restore yang menyertakan aktivitas ini akan fatal
   error. Sekarang `backup/moodle2/` lengkap dan fungsional.

6. **Capability `mod/unifair:addinstance` tidak pernah didefinisikan** di
   `db/access.php` — ini wajib ada untuk setiap activity module. Sudah
   ditambahkan, beserta `mod/unifair:view` dan `mod/unifair:choose` untuk
   kontrol akses yang lebih granular (sebelumnya cuma ada satu capability,
   `viewreport`).

## Fitur baru sesuai permintaan Anda

### Kuota bisa diatur bebas
Di halaman **Kelola Universitas**, kuota tiap universitas bisa diisi angka
berapa saja (3, 50, 200, dst.) atau **0 untuk tanpa kuota**. Tidak perlu lagi
mengandalkan textarea format `Nama|Kapasitas|1/0` yang membingungkan.

### Bisa diedit setelah dibuat
Halaman baru **`manage.php`** ("Kelola Universitas", muncul di tab dan menu
pengaturan aktivitas untuk guru/admin dengan capability
`mod/unifair:manageuni`):
- **Tambah** universitas baru kapan saja
- **Ubah** nama, kuota, dan urutan tampil
- **Hapus** (dengan konfirmasi) — otomatis membersihkan pilihan siswa terkait
- Tabel menampilkan kuota terpakai vs sisa secara real-time

Textarea bulk-input di form aktivitas sekarang hanya muncul saat **membuat**
aktivitas baru (setup cepat opsional); saat mengedit aktivitas, ada catatan
yang mengarahkan ke halaman Kelola Universitas.

## Tambahan lain (praktik produksi yang sebelumnya hilang)

- **Privacy API** (`classes/privacy/provider.php`) — wajib karena plugin ini
  menyimpan data personal pilihan siswa; sekarang mendukung ekspor data
  siswa dan penghapusan data sesuai permintaan (GDPR/right-to-be-forgotten).
- **Export laporan** sekarang pakai `\core\dataformat` (Excel/.xlsx asli),
  menggantikan trik lama yang menyamarkan file HTML sebagai `.xls`.
- Index database ditambahkan (`unifairid`, `uniid+userid` unique) untuk
  performa query dan mencegah duplikat pilihan di level database.
- Grafik Chart.js dari CDN eksternal dihapus dari `report.php` (praktik
  keamanan yang lebih baik — tidak memuat script pihak ketiga tanpa perlu).
- `FEATURE_MOD_PURPOSE` ditambahkan supaya aktivitas tampil dengan benar di
  activity chooser Moodle 4.x.
- `$plugin->requires` disesuaikan ke build Moodle 4.5 (sebelumnya menunjuk
  ke Moodle 4.1).

## Instalasi

1. Salin folder `unifair/` ke `mod/unifair/` di server Moodle Anda.
2. Buka **Site administration → Notifications** untuk menjalankan instalasi
   atau migrasi.
3. Jika Anda punya aktivitas University Fair lama, universitas yang sudah
   ada otomatis mendapat `quotaused` terisi dari jumlah pilihan siswa yang
   sudah tercatat — tidak ada data yang hilang.

## Known limitation

- Belum ada fitur waiting list / auto-promote (kalau dibutuhkan, ini cocok
  untuk permintaan terpisah — butuh tabel tambahan).
- Pengujian PHPUnit dan Behat lengkap tetap harus dijalankan di instalasi
  Moodle 4.5.6 staging milik sekolah sebelum hari acara.
