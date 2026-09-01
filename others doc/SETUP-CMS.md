# Setup Svashta Home CMS — Panduan Deploy

Semua kode sudah jadi. Ini langkah-langkah upload & konfigurasi di hosting (cPanel + phpMyAdmin, sesuai kesepakatan kita).

## 1. Struktur folder di server

```
/home/namauser/
├── shared/                    <- config.php + koneksi DB, TIDAK boleh diakses browser
│   └── config.php
├── public_html/                <- svashtahome.com (isi dari folder svashtahome/ lokal)
│   ├── index.php
│   ├── config.php
│   ├── uploads/                <- foto yang diupload lewat CMS, HARUS bisa diakses browser
│   └── pages/
└── cms.svashtahome.com/         <- subdomain admin (isi dari folder svashtahome-cms/ lokal)
    ├── config.php
    └── ...
```

`shared/` sejajar dengan `public_html/`, BUKAN di dalamnya — supaya kredensial database tidak bisa diakses lewat URL manapun.

## 2. Bikin subdomain

Di cPanel > Subdomains, buat `cms.svashtahome.com` dengan document root mengarah ke folder `cms.svashtahome.com/` (isi dari `svashtahome-cms/` lokal). Subdomain ini otomatis pakai database yang sama dengan situs utama karena keduanya include `shared/config.php` yang sama.

## 3. Import database

1. cPanel > MySQL Databases — buat database (mis. `namauser_svashtahome`) dan user DB dengan akses penuh ke database itu.
2. phpMyAdmin > pilih database tadi > tab **Import** > upload file `sql/schema.sql`.
3. Kalau nama database yang dibuat cPanel beda dari `svashtahome_cms` (biasanya cPanel kasih prefix otomatis), edit baris `CREATE DATABASE` & `USE` di paling atas `schema.sql` SEBELUM import, samakan dengan nama yang cPanel buatkan.

## 4. Isi config.php

1. Upload `shared/config.example.php` ke `shared/` di server, lalu **duplikat jadi `config.php`** (nama persis, tanpa `.example`).
2. Edit `shared/config.php`, isi `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` sesuai yang dibuat di langkah 3.
3. Pastikan `SITE_URL` dan `CMS_ADMIN_URL` sudah benar (`https://svashtahome.com` dan `https://cms.svashtahome.com`).

## 5. Upload kode

- Isi folder `svashtahome/` (lokal) → upload ke `public_html/` di server.
- Isi folder `svashtahome-cms/` (lokal) → upload ke folder subdomain `cms.svashtahome.com/` di server.
- Folder `shared/` (lokal) → upload ke `/home/namauser/shared/` (sejajar `public_html`, di luar akses web).

## 6. Permission folder upload

Folder `public_html/uploads/` harus bisa ditulis oleh PHP:
```
chmod 755 public_html/uploads
```
Kalau masih gagal upload dari admin panel, coba `chmod 775`.

## 7. Bikin akun admin pertama

1. Buka `https://cms.svashtahome.com/setup-create-admin.php`
2. Isi username + password (minimal 8 karakter)
3. **Setelah berhasil, HAPUS file `setup-create-admin.php` dari server** — kalau dibiarkan, siapapun yang tahu URL-nya bisa bikin akun admin baru.
4. Login di `https://cms.svashtahome.com/login.php`

## 8. Migrasi 66 produk lama (opsional tapi disarankan)

66 halaman produk statis yang lama (`gallery_sofa_adhum.html` dkk) masih ada dan tetap bisa diakses — belum dihapus, jadi situs tidak rusak selama proses ini. Untuk memindahkan kontennya ke database supaya bisa dikelola lewat CMS:

1. Login ke admin panel.
2. Buka `https://cms.svashtahome.com/migrate-legacy-products.php`
3. Script otomatis baca semua `gallery_*.html`, tarik nama/deskripsi/foto/spesifikasi, dan masukkan ke tabel `products`. Aman dijalankan berkali-kali (yang sudah ada di-skip, tidak dobel).
4. Cek hasilnya di menu **Products** — kalau ada yang kelewat/salah parse (ditandai SKIP atau ERROR di laporan), lengkapi manual lewat form Add/Edit Product.
5. **Hapus file `migrate-legacy-products.php`** setelah selesai dicek.

Gambar hasil migrasi TIDAK di-copy ke folder `uploads/` — tetap mereferensikan lokasi asli di `assets/img/gallery/...`, jadi tidak makan storage dobel.

## 9. Cek halaman publik

Setelah ada minimal 1 produk di database:
- `https://svashtahome.com/index.php` — hero/video/collaborators/reviews harusnya sudah narik dari CMS (kalau belum diisi datanya, otomatis fallback ke konten lama, situs tidak kosong)
- `https://svashtahome.com/pages/category.php?cat=sofa` — daftar produk per kategori
- `https://svashtahome.com/pages/product.php?slug=adhum-sofa` — detail produk
- `https://svashtahome.com/pages/blog.php`, `projects-list.php`, `custom_order.php`

## 10. Terakhir: arahkan domain utama ke index.php

Situs sekarang punya `index.html` (lama, statis) DAN `index.php` (baru, dinamis) berdampingan. Kalau server default-nya memprioritaskan `index.html`, homepage yang dinamis tidak akan kepakai. Cek urutan `DirectoryIndex` di hosting (biasanya di `.htaccess`), pastikan `index.php` didahulukan:
```
DirectoryIndex index.php index.html
```
Atau lebih aman: setelah yakin `index.php` jalan lancar, **hapus/rename `index.html`** supaya tidak ambigu.

---

## Kalau ada error

- **"Sesi tidak valid"** saat submit form → cookie/session PHP belum jalan, cek `session.save_path` di hosting bisa ditulis PHP.
- **Halaman putih kosong** → aktifkan `display_errors` sementara di `shared/config.php` (`ini_set('display_errors', 1);` di baris paling atas) buat lihat error aslinya, matikan lagi kalau sudah live ke publik.
- **Foto tidak muncil setelah upload** → cek permission folder `uploads/`, dan cek `UPLOAD_URL_BASE` di config.php sudah pas dengan domain asli.
