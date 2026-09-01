# Dokumentasi Arsitektur

## 1. Multi-tenant: User, Organization, Role

Satu identitas `users` (global, cuma 1 row per orang) bisa jadi anggota banyak `organizations` sekaligus, dengan `role` yang beda-beda di tiap organisasi. Session nyimpen 2 lapis:

- `$_SESSION['uo_user']` — siapa yang login (id/name/email).
- `$_SESSION['uo_active']` — organisasi aktif yang lagi dipilih (kalau user cuma 1 org, langsung auto-aktif pas login). Isinya termasuk `role_id`, `role_name`, `is_production_role`, dan `warehouse_id` (lihat bagian 5).

Tabel penghubungnya `user_organization_roles` (user_id + organization_id + role_id + status + warehouse_id).

`roles` per-organisasi (bukan global) — tiap organisasi bikin role sendiri-sendiri. Role `Owner` otomatis dibuat pas signup dan dikasih akses penuh ke semua modul.

## 2. Matrix hak akses modul

`MODULES` (const di `modules.php`) daftar 14 modul (10 modul umum + 4 modul Manufaktur). Tiap role punya baris di `role_module_access` per modul: `can_view`, `can_create`, `can_edit`, `can_delete`, `can_print`.

Dicek pakai:
```php
require_module_access('manufaktur_surat_jalan');           // default: can_view
require_module_access('manufaktur_surat_jalan', 'can_edit');
has_access('manufaktur_surat_jalan', 'can_delete');         // versi boolean, buat sembunyiin tombol
```

Matrix ini di-cache ke `$_SESSION['uo_access']` pas `switch_org()` — ganti role/lokasi seorang user gak langsung kepakai sampe dia re-login atau ganti organisasi.

## 3. Modul Manufaktur vs modul lama

Aplikasi ini awalnya modul umum ERP (Penawaran, Invoicing, PO, SPK, dst — semua nempel ke `contacts`, `projects`, `products`, `materials` yang sama). Grup modul **Manufaktur** ditambahin belakangan buat alur kerja spesifik manufaktur (Form Penawaran Harga → Form PO/Product Series → Form Surat Jalan → Form Label), tabelnya prefix `manufaktur_*` biar gak nyampur sama modul lama.

**Penting:** `products` (Master Barang) itu **shared** antara modul lama dan Manufaktur — banyak tabel modul lama (`quotation_lines`, `po_lines`, `goods_receipt_lines`, `invoice_lines`, `delivery_order_lines`, `material_request_lines`, `spk`) punya FK ke `products.id`. Kalau mau bulk-delete/reset data produk, WAJIB cek dulu row mana yang masih direferensiin modul lama (lihat pola di `manufaktur-surat-jalan.php` fungsi cek FK sebelum DELETE), jangan asal `TRUNCATE`.

## 4. Stock Ledger (FIFO, multi-lokasi)

`stock_ledger` (org + warehouse + product + direction in/out + qty + qty_remaining + unit_cost + ref_type + ref_id) adalah **satu-satunya sumber kebenaran** stok. Gak ada kolom "qty" langsung di `products` — semua dihitung dari SUM `stock_ledger.qty_remaining` per produk per lokasi.

Helper di `stock.php`:
- `fifo_consume_stock()` — konsumsi stok FIFO (ambil dari batch `in` paling lama dulu), balikin weighted unit cost, throw kalau stok kurang.
- `available_stock()` — sisa stok tersedia.

`ref_type` yang dipakai: `opening_balance`, `goods_receipt`, `delivery_order`, `spk_material`, `manufaktur_surat_jalan` — buat traceability (Kartu Stok di halaman Product Info bisa link balik ke dokumen sumbernya).

Surat Jalan ngurangin stok FIFO **cuma pas dokumen pertama kali dibuat** (bukan pas diedit ulang), biar gak dobel-kurang tiap kali disimpan.

## 5. Pembatasan lokasi per user

Anggota organisasi (selain role Owner) bisa di-assign ke 1 lokasi/gudang spesifik lewat halaman Master User (`users.php`) — kolom `user_organization_roles.warehouse_id`. `NULL` berarti gak dibatasin (liat semua lokasi); ini SELALU `NULL` buat Owner gak peduli kolomnya keisi apa.

Helper: `user_location_restriction(): ?int` (di `auth.php`) — balikin `warehouse_id` kalau user dibatasin, `null` kalau bebas. Dipake buat:
- Filter rail/list dokumen (Surat Jalan, Saldo Awal) ke lokasi user aja.
- Filter chip lokasi di Laporan Inventory.
- Kunci field "Lokasi"/"Gudang Asal" di form (readonly di UI + di-paksa ulang di server biar gak bisa di-bypass devtools).

## 6. Konvensi nomor dokumen

Tabel `doc_counters` (organization_id + doc_type + year + last_number), pola locking `SELECT ... FOR UPDATE` di dalam transaction biar aman dari race condition kalau 2 orang nyimpen bareng. Tiap modul punya `next_*_number()` sendiri.

**Gotcha yang udah kejadian:** kolom `doc_type` collation-nya `utf8mb4_0900_ai_ci` (case-insensitive) — jangan pakai doc_type yang beda cuma di kapitalisasi (`'PO'` vs `'po'`) buat modul yang beda, soalnya bakal keanggep SAMA row di `doc_counters` dan nomornya kebagi tanpa sengaja. Manufaktur PO makanya pakai `'MPO'`, bukan `'PO'` (yang udah kepake modul lama).

## 7. Soft-delete

Dokumen transaksi & master data pakai `deleted_by` + `deleted_at`, ditandain "void"/diarsipkan, HAMPIR GAK PERNAH hard-delete — kecuali data yang beneran gak direferensiin apa-apa (lihat bagian 3 soal `products`).

## 8. Gotcha teknis hosting (shared cPanel, LiteSpeed)

- **php.ini `output_buffering=4096`** — bikin `ob_get_level()` udah balik `1` dari awal request (buffer implisit bawaan server, bukan punya kita). File yang `require includes/header.php` (langsung ngeluarin HTML sidebar) SEBELUM cek POST+redirect, kalau HTML-nya lewat 4096 byte, header udah kekirim duluan sebelum sempet `header('Location: ...')` — gagal diem-diem ("headers already sent", gak keliatan user, redirect abis-simpan jadi gak jalan). Fix-nya: `ob_start()` TANPA GUARD `if (!ob_get_level())` di awal `auth.php` (guard itu salah — dia ngecek buffer implisit yang udah ada, bukan punya kita, jadi `ob_start()` kita malah gak pernah kepanggil).
- **OPcache staleness** — edit ke file yang di-`require_once` dari file lain (terutama `backoffice-shared/*.php`) kadang gak langsung kepake abis re-upload lewat FTP. Perlu `opcache_reset()` eksplisit. Di server ini prosesnya multi-worker (LiteSpeed/LSAPI) — 1x `opcache_reset()` cuma ngereset worker yang nanganin request itu doang, jadi perlu dipanggil BERKALI-KALI (~10x) biar kena semua worker pool-nya.
- Gak ada Composer/dependency eksternal — `.xlsx` writer (`xlsx_writer.php`) dan reader (`xlsx_reader.php`) ditulis dari nol pakai `ZipArchive` + `GD` + `SimpleXML` bawaan PHP.

## 9. Mode embed (popup dialog)

`includes/header.php` support `$embedMode = true` (di-set SEBELUM `require`) — skip render sidebar+topbar, dipake buat nampilin halaman penuh (misal Product Info) di dalam `<iframe>` popup modal tanpa dobel chrome. Redirect di dalam halaman yang embed-mode harus ikut nyelipin `&embed=1` biar gak balik ke tampilan penuh di tengah iframe.
