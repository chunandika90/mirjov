# Menyiapkan project ini di laptop baru

Panduan ini **tidak memuat satu pun password, token, atau kunci**. Repo ini publik —
apa pun yang ditulis di sini bisa dibaca siapa saja. Yang ada di bawah hanya daftar
langkah dan **di mana** tiap rahasia diambil.

---

## 1. Yang perlu dipasang

| Perangkat | Keterangan |
|---|---|
| **Git** | — |
| **XAMPP 8.2** | memberi PHP 8.2 sekaligus phpMyAdmin — `winget install -e --id ApacheFriends.Xampp.8.2` |
| **MariaDB / MySQL** | boleh pakai bawaan XAMPP, atau yang sudah ada di laptop |
| **Node.js** | hanya kalau mau membuat ulang deck PPT panduan |
| **GitHub CLI** (`gh`) | mempermudah urusan Actions & secrets |

Setelah XAMPP terpasang, nyalakan dua ekstensi di `C:\xampp\php\php.ini` — keduanya
mati secara bawaan dan dipakai untuk unggah foto:

```ini
extension=gd
extension=zip
```

## 2. Ambil kodenya

```bash
git clone https://github.com/chunandika90/mirjov.git
cd mirjov
```

Dua branch yang dipakai:

- **`main`** — yang dideploy ke server. Website di sini masih versi sebelum batch Canva.
- **`website-canva`** — perubahan website dari catatan Canva, belum disetujui naik.

## 3. Database lokal

Tiga sistem, dua database:

```bash
# Website + CMS
mysql -u root -e "CREATE DATABASE svashtahome_cms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root svashtahome_cms < sql/schema.sql
mysql -u root svashtahome_cms < sql/2026-10-04-blog-quote.sql
mysql -u root svashtahome_cms < sql/2026-10-05-website-canva-batch.sql

# Backoffice
mysql -u root -e "CREATE DATABASE mirjov_backoffice_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root mirjov_backoffice_local < sql/backoffice-ALL-COMBINED.sql
mysql -u root mirjov_backoffice_local < sql/backoffice-schema-v3-request-material.sql
```

> **Perhatian:** tabel-tabel modul Manufaktur (`manufaktur_penawaran`, `manufaktur_po`,
> `manufaktur_surat_jalan`, `manufaktur_label`, beserta tabel barisnya, dan
> `stock_opening_balance`) **tidak ada file migrasinya di repo ini** — hanya hidup di
> database server. Untuk menjalankan modul Manufaktur secara lokal, ambil strukturnya
> dari phpMyAdmin server: **Export → Custom → Structure only**, pilih tabel-tabel itu.
> Ini juga berarti struktur modul Manufaktur tidak bisa dibangun ulang dari repo kalau
> database server hilang — sebaiknya file itu dijadikan migrasi resmi di `sql/`.

## 4. File konfigurasi

Tiga file ini **sengaja tidak masuk git** karena memuat kredensial. Harus dibuat ulang
di laptop baru dengan menyalin dari contohnya:

| Salin dari | Jadikan | Isi dengan |
|---|---|---|
| `shared/config.example.php` | `shared/config.php` | kredensial MySQL lokal; `SITE_URL` ke `http://localhost:8090` |
| `backoffice-shared/config.example.php` | `backoffice-shared/config.php` | biarkan apa adanya — file ini otomatis memakai config.local saat di localhost |
| `backoffice-shared/config.local.example.php` | `backoffice-shared/config.local.php` | nama database backoffice lokal |

Untuk `shared/config.php`, arahkan `UPLOAD_URL_BASE` ke `https://svashtahome.com/uploads`
supaya foto produk tampil di lokal tanpa perlu menyalin folder `uploads/`.

## 5. Menjalankan

`.claude/launch.json` sudah memuat tiga server:

| Port | Isi |
|---|---|
| 8090 | situs publik |
| 8091 | CMS |
| 8092 | backoffice |

Atau jalankan manual:

```bash
C:/xampp/php/php.exe -S localhost:8090 -t svashtahome
```

---

## 6. Di mana tiap rahasia diambil

**Tidak satu pun dari ini boleh ditulis ke dalam repo.**

| Rahasia | Dipakai untuk | Cara mendapatkannya di laptop baru |
|---|---|---|
| Password FTP cPanel | deploy otomatis | sudah tersimpan sebagai GitHub Secret di repo — **tidak perlu dipindah sama sekali**. Actions yang memakainya, bukan laptop lo. Kalau lupa: cPanel → FTP Accounts → Change Password |
| Kredensial database server | config di server | sudah ada di `shared/config.php` **di server**, bukan di laptop. Jangan diunduh |
| Personal Access Token GitHub | push dari laptop | **buat token baru** di github.com/settings/personal-access-tokens untuk laptop baru. Jangan menyalin yang lama — token itu terikat ke satu perangkat sebaiknya. Izin yang dibutuhkan: Contents (read/write), Workflows (read/write) |
| Login CMS & backoffice produksi | mengelola konten | akun masing-masing orang; tidak ada hubungannya dengan setup laptop |
| Password MySQL lokal | database di laptop | lo yang menentukan sendiri saat memasang XAMPP |

### Kenapa tidak disimpan di repo

Repo ini **publik**. File yang pernah di-commit tetap ada di riwayat git walaupun
dihapus di commit berikutnya — jadi sekali sebuah password masuk, satu-satunya
pemulihan yang benar adalah mengganti password itu, bukan menghapus filenya.

`.gitignore` sudah memblokir `shared/config.php`, `backoffice-shared/config.php`,
`backoffice-shared/config.local.php`, dan semua `.sql` di folder root (dump database
biasanya memuat hash password admin dan data pelanggan).

---

## 7. Setelah selesai

Periksa cepat bahwa tidak ada rahasia yang ikut ter-stage sebelum commit pertama:

```bash
git status --short
git check-ignore -v shared/config.php backoffice-shared/config.php
```

Perintah kedua harus menyebut `.gitignore` untuk kedua file. Kalau tidak, **jangan
commit** — laporkan dulu.
