# Mirjov Backoffice ERP

Backoffice ERP internal buat **Mirjov Karunia Abadi** — PHP prosedural (tanpa framework) + MySQL, di-hosting di cPanel shared hosting (`backoffice.svashtahome.com`).

Awalnya modul-modul umum ERP (Penawaran, Invoicing, PO, SPK, Penerimaan Barang, Delivery Order, Kuitansi), lalu ditambahin satu grup modul baru **Manufaktur** yang jadi fokus pengembangan saat ini: Form Penawaran Harga, Form Purchase Order (Product Series), Form Surat Jalan, Form Label, Inventory (stok multi-lokasi dengan FIFO costing), dan Input Saldo Awal.

## Struktur folder

```
backoffice-shared/          Kode & config yang di-share, DILUAR webroot subdomain
  config.php                 Kredensial database ASLI — TIDAK di-commit (lihat Setup)
  config.example.php          Template config.php, aman di-commit
  config.local.example.php    Template buat dev lokal (php -S), aman di-commit
  db.php                      Koneksi PDO singleton
  auth.php                    Session, login, multi-tenant org/role, CSRF, lokasi restriction
  modules.php                 Daftar modul (MODULES const) buat matrix akses & sidebar
  stock.php                   Helper FIFO stock ledger (consume/available per lokasi)
  doc_number.php               -
  image_upload.php            Resize & simpan foto produk
  xlsx_writer.php             Penulis .xlsx MINIMAL dari nol (ZipArchive+GD, gak ada Composer)
  xlsx_reader.php             Pembaca .xlsx pasangannya (buat upload template Saldo Awal)
  label_icons.php             SVG ikon fragile/handle-with-care/keep-dry buat print label
  material_request.php        -

backoffice.svashtahome.com/  Webroot subdomain — semua halaman .php + assets
  includes/header.php          Layout (sidebar + topbar), support mode embed (popup/iframe)
  includes/footer.php          Penutup layout
  assets/css/app.css           Semua styling
  assets/js/app.js             Combobox, modal, delete-form helper
  manufaktur-*.php             Modul Manufaktur (Penawaran, PO, Surat Jalan, Label, Saldo Awal, dst)
  master-barang-kanban.php     Master Barang kanban board (Kategori → Sub Kategori → Barang)
  inventory-report.php         Laporan stok per lokasi, drill-down per kategori
  products.php                 Master Barang form lengkap (spek/foto/harga/tier) — legacy view
  warehouses.php, contacts.php, projects.php, ...   Master data
  quotations.php, invoices.php, purchase-orders.php, spk.php, ...   Modul lama (non-Manufaktur)
```

## Setup

1. Copy `backoffice-shared/config.example.php` → `backoffice-shared/config.php`, isi kredensial database asli dari cPanel.
2. Deploy `backoffice-shared/` **di luar** webroot (sejajar folder subdomain), dan `backoffice.svashtahome.com/` sebagai webroot subdomain-nya.
3. Server butuh ekstensi PHP: `pdo_mysql`, `zip`, `gd`, `simplexml`, `dom`.

Gak ada `composer install` / dependency eksternal — semua ditulis dari nol (termasuk `.xlsx` reader/writer) karena hosting-nya gak kasih akses Composer.

## Arsitektur

Lihat [`DOKUMENTASI_ARSITEKTUR.md`](DOKUMENTASI_ARSITEKTUR.md) buat detail: multi-tenant org/role, matrix hak akses, stock ledger FIFO, dan konvensi kode yang dipakai konsisten di semua modul.

## Konvensi cepat

- Nomor dokumen: tabel `doc_counters` (per organisasi + tipe dokumen + tahun), pola `SELECT ... FOR UPDATE` biar aman dari race condition.
- Soft-delete: kolom `deleted_by`/`deleted_at`, hampir gak ada hard-DELETE (kecuali data yang bener-bener gak direferensiin tabel lain).
- CSRF: `csrf_field()` di tiap form, `require_csrf()` di tiap POST handler.
- Combobox reusable (`initCombobox` di `app.js`) — ketik value baru otomatis bikin master record baru lewat pola `find_or_create_*()`.
