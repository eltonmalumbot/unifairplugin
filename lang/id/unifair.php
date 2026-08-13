<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Indonesian language strings for mod_unifair.
 *
 * @package     mod_unifair
 * @copyright   2026 BPK PENABUR Jakarta
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'University Fair';
$string['modulename'] = 'University Fair';
$string['modulenameplural'] = 'University Fair';
$string['modulename_help'] = 'Memungkinkan siswa memilih universitas (atau item berkuota apa pun) pada acara education fair.';
$string['pluginadministration'] = 'Administrasi University Fair';

$string['configunifair'] = 'Pengaturan University Fair';
$string['maxchoices'] = 'Maksimal pilihan per siswa';
$string['maxchoicesvalidation'] = 'Maksimal pilihan minimal 1.';
$string['universitiestext'] = 'Universitas (setup cepat massal)';
$string['universitiestext_help'] = 'Opsional: tambahkan beberapa universitas sekaligus saat membuat aktivitas. Satu per baris, format: <br><b>Nama|Kuota</b><br><br>Contoh:<br>Universitas Indonesia|50 (kuota 50)<br>Universitas Terbuka|0 (tanpa kuota / unlimited)<br><br>Anda bisa menambah, mengubah, atau menghapus universitas satu per satu kapan saja setelahnya dari halaman "Kelola Universitas".';
$string['manageuninotice'] = 'Universitas dikelola dari halaman "Kelola Universitas", tersedia setelah aktivitas disimpan.';
$string['unifairopen'] = 'Waktu buka';
$string['unifairclose'] = 'Waktu tutup';
$string['timeopenclosevalidation'] = 'Waktu tutup harus setelah waktu buka.';
$string['viewtab'] = 'Tampilan Siswa';
$string['reporttab'] = 'Laporan Guru';

$string['session'] = 'Sesi';
$string['managesessions'] = 'Kelola Sesi';
$string['addsession'] = 'Tambah Sesi';
$string['editsession'] = 'Ubah Sesi';
$string['sessionname'] = 'Nama Sesi';
$string['sessiondescription'] = 'Deskripsi';
$string['defaultsessionname'] = 'Sesi 1';
$string['migratedsessionname'] = 'Sesi Default (Data Lama)';
$string['nosessions'] = 'Belum ada sesi.';
$string['createsessionfirst'] = 'Buat minimal satu sesi sebelum menambahkan universitas.';
$string['sessionhasnouniversities'] = 'Sesi ini belum memiliki universitas. Hubungi panitia untuk melengkapi pengaturan.';
$string['confirmdeletesession'] = 'Yakin ingin menghapus sesi "{$a}"?';
$string['sessiondeleteblocked'] = 'Sesi ini tidak dapat dihapus karena masih berisi {$a} universitas. Pindahkan atau hapus universitas tersebut terlebih dahulu.';
$string['sessiondeleted'] = 'Sesi "{$a}" berhasil dihapus.';
$string['sessionconfigsaved'] = 'Sesi "{$a}" berhasil disimpan.';
$string['reorder'] = 'Geser';
$string['reorderhelp'] = 'Geser ikon pada kolom Geser untuk mengubah urutan sesi. Urutan tersimpan otomatis dan hanya dapat diubah dalam hari yang sama.';
$string['dragsession'] = 'Geser untuk mengubah urutan sesi';
$string['dragsessionname'] = 'Geser sesi {$a} untuk mengubah urutannya';
$string['savingorder'] = 'Menyimpan urutan sesi...';
$string['sessionordersaved'] = 'Urutan sesi berhasil disimpan.';
$string['sessionordererror'] = 'Urutan sesi gagal disimpan. Halaman akan dimuat ulang.';
$string['error_reorderbusy'] = 'Urutan sesi sedang diubah oleh pengguna lain. Silakan coba lagi.';
$string['invalidsession'] = 'Pilih sesi yang valid.';
$string['sessionchangeblocked'] = 'Universitas yang sudah memiliki pilihan siswa tidak dapat dipindahkan ke sesi lain. Hapus pilihan tersebut terlebih dahulu.';

$string['manageuni'] = 'Kelola Universitas';
$string['adduni'] = 'Tambah Universitas';
$string['edituni'] = 'Ubah Universitas';
$string['uniname'] = 'Nama';
$string['capacity'] = 'Kuota';
$string['capacity_help'] = 'Jumlah maksimum siswa yang dapat memilih item ini. Isi <b>0</b> untuk tanpa batas kuota (unlimited).';
$string['capacityvalidation'] = 'Kuota tidak boleh negatif.';
$string['sortorder'] = 'Urutan';
$string['quotaused'] = 'Terpakai';
$string['remaining'] = 'Sisa';
$string['unlimited'] = 'Tanpa kuota';
$string['actions'] = 'Aksi';
$string['nouniversities'] = 'Belum ada universitas. Klik "Tambah Universitas" untuk menambahkan.';
$string['confirmdeleteuni'] = 'Yakin ingin menghapus "{$a}"? Semua pilihan siswa untuk item ini juga akan terhapus. Tindakan ini tidak dapat dibatalkan.';
$string['unisaved'] = 'Universitas "{$a}" berhasil disimpan.';
$string['unideleted'] = 'Universitas "{$a}" berhasil dihapus.';
$string['backtoactivity'] = '&laquo; Kembali ke aktivitas';

$string['pickmax'] = 'Pilih maksimal {$a} Universitas:';
$string['pickonepersession'] = 'Pilih tepat satu universitas pada setiap sesi.';
$string['savechoices'] = 'Simpan Pilihan Saya';
$string['choicessaved'] = 'Pilihan Anda berhasil disimpan.';
$string['savesession'] = 'Simpan Sesi Ini';
$string['sessionsaved'] = 'Pilihan untuk {$a} berhasil disimpan.';
$string['error_sessionclosed'] = 'Sesi ini belum dibuka atau sudah ditutup.';
$string['error_activityclosed'] = 'Aktivitas University Fair ini belum dibuka atau sudah ditutup.';
$string['quotafullshort'] = 'KUOTA PENUH';
$string['notopenyet'] = 'Pemilihan belum dibuka. Akan dibuka pada {$a}.';
$string['alreadyclosed'] = 'Pemilihan sudah ditutup sejak {$a}.';
$string['nopermissiontochoose'] = 'Anda tidak memiliki izin untuk membuat pilihan pada aktivitas ini.';

$string['error_toomanychoices'] = 'Anda hanya boleh memilih maksimal {$a} universitas.';
$string['error_someitemsfull'] = 'Tidak ada pilihan yang diubah karena kuota universitas berikut menjadi penuh saat disimpan: {$a}. Pilih universitas lain lalu coba lagi.';
$string['error_invalidchoices'] = 'Pilihan yang dikirim tidak valid. Muat ulang halaman lalu coba lagi.';
$string['error_onepersession'] = 'Anda hanya boleh memilih satu universitas per sesi.';
$string['error_allsessionsrequired'] = 'Pilih tepat satu universitas di setiap sesi sebelum menyimpan.';
$string['error_choicebusy'] = 'Pilihan Anda sedang diproses oleh permintaan lain. Silakan coba lagi.';

$string['summarystats'] = 'Statistik Ringkas';
$string['totalitems'] = 'Total Universitas';
$string['totalsessions'] = 'Total Sesi';
$string['totalchoices'] = 'Total Pilihan';
$string['totalstudents'] = 'Total Siswa yang Sudah Memilih';
$string['perunibreakdown'] = 'Detail Pilihan Per Universitas';
$string['perstudentbreakdown'] = 'Detail Pilihan Per Siswa';
$string['percentagefull'] = 'Persentase';
$string['status'] = 'Status';
$string['almostfull'] = 'Hampir Penuh';
$string['available'] = 'Tersedia';
$string['exportxlsx'] = 'Export ke Excel';
$string['timecreated'] = 'Waktu Pilih';

$string['unifair:addinstance'] = 'Menambahkan aktivitas University Fair baru';
$string['unifair:view'] = 'Melihat aktivitas University Fair';
$string['unifair:choose'] = 'Mengajukan pilihan universitas';
$string['unifair:manageuni'] = 'Mengelola universitas (tambah/ubah/hapus)';
$string['unifair:viewreport'] = 'Melihat laporan University Fair';
$string['unifair:managechoices'] = 'Mengubah pilihan University Fair siswa';
$string['unifair:manageattendance'] = 'Mengubah kehadiran University Fair';

$string['privacy:metadata'] = 'Plugin University Fair menyimpan pilihan universitas setiap siswa.';
$string['privacy:metadata:unifair_choice'] = 'Informasi tentang pilihan universitas seorang siswa.';
$string['privacy:metadata:unifair_choice:uniid'] = 'Universitas yang dipilih.';
$string['privacy:metadata:unifair_choice:userid'] = 'ID pengguna yang membuat pilihan.';
$string['privacy:metadata:unifair_choice:timecreated'] = 'Waktu pilihan dibuat.';

// Fitur versi 3.1.
$string['sessiontimeopen'] = 'Waktu sesi dibuka';
$string['sessiontimeclose'] = 'Waktu sesi ditutup';
$string['error_closebeforeopen'] = 'Waktu tutup harus lebih akhir daripada waktu buka.';
$string['availability'] = 'Waktu tersedia';
$string['always'] = 'Tanpa batas';
$string['sessionwindowdisplay'] = 'Buka: {$a->open}; Tutup: {$a->close}';
$string['sessionnotopen'] = 'Sesi ini akan dibuka pada {$a}.';
$string['sessionclosed'] = 'Sesi ini telah ditutup pada {$a}.';
$string['remainingplaces'] = 'Tersisa {$a} kursi';
$string['managechoices'] = 'Ubah pilihan siswa';
$string['selectstudent'] = 'Pilih siswa';
$string['teacherchoicesaved'] = 'Pilihan siswa berhasil disimpan.';
$string['attendance'] = 'Kehadiran';
$string['selectsession'] = 'Pilih sesi';
$string['unmarked'] = 'Belum ditandai';
$string['present'] = 'Hadir';
$string['absent'] = 'Tidak hadir';
$string['excused'] = 'Izin';
$string['attendancesaved'] = 'Kehadiran berhasil disimpan.';
$string['error_attendancebusy'] = 'Kehadiran sedang diperbarui oleh proses lain. Coba kembali sebentar lagi.';
$string['incompletestudents'] = 'Siswa dengan pilihan belum lengkap';
$string['incompletestudentlist'] = 'Siswa yang belum memilih pada semua sesi';
$string['importdata'] = 'Impor sesi dan universitas';
$string['importfile'] = 'File CSV atau XLSX';
$string['import'] = 'Impor';
$string['downloadtemplate'] = 'Unduh template CSV';
$string['importinstructions'] = 'Kolom wajib: session, university, capacity. Kolom opsional: session_description, session_sortorder, timeopen, timeclose, university_sortorder. Setiap baris universitas dibuat sebagai pilihan terpisah, termasuk jika namanya sama. Gunakan format tanggal DD/MM/YYYY HH:MM atau YYYY-MM-DD HH:MM.';
$string['importsummary'] = 'Impor selesai: {$a->createdsessions} sesi dibuat, {$a->createdunis} universitas dibuat, {$a->updatedunis} universitas diperbarui.';
$string['importrowerror'] = 'Baris {$a}: sesi, universitas, atau kuota tidak valid.';
$string['importrowtimeerror'] = 'Baris {$a}: waktu tutup harus lebih akhir daripada waktu buka.';
$string['importrowdateerror'] = 'Baris {$a}: format tanggal tidak valid. Gunakan DD/MM/YYYY HH:MM atau YYYY-MM-DD HH:MM.';
$string['error_importheaders'] = 'File impor tidak memiliki kolom wajib: session, university, atau capacity.';
$string['error_invalidspreadsheet'] = 'File XLSX tidak dapat dibaca.';
$string['error_invalidfiletype'] = 'Unggah file CSV atau XLSX.';
$string['error_importtoomanyrows'] = 'Impor dibatasi maksimal 5.000 baris data.';
$string['error_xlsxunavailable'] = 'Impor XLSX tidak tersedia di server ini. Gunakan template CSV.';
$string['error_importbusy'] = 'Impor lain sedang berjalan untuk aktivitas ini. Coba kembali sebentar lagi.';
$string['deleteallunis'] = 'Hapus Semua Universitas';
$string['confirmdeleteallunis'] = 'Yakin ingin menghapus seluruh {$a} universitas? Semua sesi, pilihan siswa, dan data kehadiran pada aktivitas ini juga akan dihapus. Deskripsi aktivitas tetap dipertahankan. Tindakan ini tidak dapat dibatalkan.';
$string['allunisdeleted'] = 'Seluruh {$a} universitas, sesi, dan data terkait berhasil dihapus.';
$string['selectallunis'] = 'Pilih semua universitas';
$string['selectuni'] = 'Pilih {$a}';
$string['deleteselectedunis'] = 'Hapus yang Dipilih';
$string['noselectedunis'] = 'Pilih minimal satu universitas yang akan dihapus.';
$string['confirmdeleteselectedunis'] = 'Yakin ingin menghapus {$a} universitas yang dipilih? Pilihan siswa dan data kehadiran yang terkait juga akan dihapus. Tindakan ini tidak dapat dibatalkan.';
$string['selectedunisdeleted'] = '{$a} universitas yang dipilih beserta pilihan terkait berhasil dihapus.';
$string['sortascending'] = 'Urutkan {$a} dari kecil ke besar atau A ke Z';
$string['sortdescending'] = 'Urutkan {$a} dari besar ke kecil atau Z ke A';
$string['choicelockednotice'] = 'Pilihan sesi ini sudah disimpan dan dikunci. Hubungi guru jika perlu diperbaiki.';
$string['error_choicealreadylocked'] = 'Pilihan sesi ini sudah disimpan dan tidak dapat diganti oleh siswa. Hubungi guru jika perlu diperbaiki.';
$string['eventchoiceupdated'] = 'Pilihan universitas diperbarui';
$string['eventattendanceupdated'] = 'Kehadiran diperbarui';
$string['eventsessionupdated'] = 'Konfigurasi sesi diperbarui';
$string['eventuniversityupdated'] = 'Konfigurasi universitas diperbarui';
$string['eventdataimported'] = 'Sesi dan universitas diimpor';
$string['privacy:metadata:unifair_attendance'] = 'Catatan kehadiran siswa pada sebuah sesi.';
$string['privacy:metadata:unifair_attendance:userid'] = 'Siswa yang kehadirannya dicatat.';
$string['privacy:metadata:unifair_attendance:status'] = 'Status kehadiran.';
$string['privacy:metadata:unifair_attendance:timemodified'] = 'Waktu status kehadiran diubah.';
$string['privacy:metadata:unifair_attendance:modifiedby'] = 'ID pengguna yang terakhir mengubah status kehadiran.';
