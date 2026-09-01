<?php
/**
 * Konfigurasi bersama — dipakai svashtahome/ (situs utama) DAN
 * svashtahome-cms/ (admin panel di subdomain), lewat include relatif
 * ke folder ini.
 *
 * SETUP DI HOSTING:
 * 1. Salin file ini jadi "config.php" (di folder yang SAMA, "shared/"),
 *    lalu isi kredensial database asli dari cPanel > MySQL Databases.
 * 2. Taruh folder "shared/" di LUAR public_html DAN di luar folder
 *    subdomain — supaya tidak bisa diakses langsung lewat browser.
 *    Di cPanel biasanya ini artinya sejajar dengan public_html, bukan
 *    di dalamnya:
 *      /home/namauser/shared/config.php        <- di sini
 *      /home/namauser/public_html/              <- svashtahome.com
 *      /home/namauser/cms.svashtahome.com/       <- subdomain admin
 * 3. "config.php" (bukan .example.php) sengaja TIDAK dimasukkan ke
 *    dalam kode yang di-share/upload ke tempat publik — isinya kredensial
 *    asli, treat seperti password.
 */

// DEBUG_MODE: tampilkan error PHP asli di layar (bukan halaman 500 kosong)
// di SEMUA halaman (admin + situs), karena config.php ini ke-load paling
// duluan di setiap file. WAJIB diganti ke `false` sebelum situs ini dipakai
// beneran oleh publik/customer — error PHP mentah bisa bocorin detail
// struktur kode/database ke siapapun yang lihat.
define('DEBUG_MODE', true);
if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

define('DB_HOST', 'localhost');
define('DB_NAME', 'svashtahome_cms');
define('DB_USER', 'GANTI_DENGAN_USER_DB');
define('DB_PASS', 'GANTI_DENGAN_PASSWORD_DB');

// SITE_DIR_BASE = path folder situs utama di server (tempat index.php,
// folder pages/, uploads/, dst). SEMUA path lain (upload, migrasi) dibaca
// dari sini — jadi kalau situs pindah folder/domain, cukup ubah 1 baris ini.
// Isi dengan NAMA FOLDER ASLI di server (mis. "public_html" kalau di domain
// utama, atau nama folder subdomain kalau masih testing di subdomain).
define('SITE_DIR_BASE', __DIR__ . '/../GANTI_DENGAN_NAMA_FOLDER_SITUS');
define('SITE_URL', 'https://GANTI_DENGAN_DOMAIN_SITUS');

// Folder upload gambar HARUS bisa diakses browser (beda dari config.php
// ini sendiri yang harus disembunyikan) — lokasinya di DALAM folder situs:
//   /home/namauser/public_html/uploads/   <- foto produk dsb, publik
//   /home/namauser/shared/config.php       <- kredensial, tersembunyi
define('UPLOAD_URL_BASE', SITE_URL . '/uploads');
define('UPLOAD_DIR_BASE', SITE_DIR_BASE . '/uploads');

define('CMS_ADMIN_URL', 'https://cms.svashtahome.com');
