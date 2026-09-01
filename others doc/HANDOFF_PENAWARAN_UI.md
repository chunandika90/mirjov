# Handoff — Modul Transaksi Backoffice (Wujud ERP)

Prototype UI: `Penawaran.dc.html` (single-file React-like component, inline styles, semua state di-mock di JS — untuk referensi visual/interaksi, BUKAN kode produksi). Base context asli ada di `uploads/BACKOFFICE_UI_HANDOFF.md`.

## Struktur Umum
- Sidebar kiri fixed (dark), grouped: Transaksi / Master Data / Project / Admin. Mobile: hamburger + drawer.
- Setiap modul transaksi (Penawaran, Invoicing, Request Material, Purchase Order) pakai **pola rail 22% + detail 78%**:
  - Rail kiri: filter bulan (‹ Label Bulan › + tombol "Bulan Ini"), list dokumen (klik untuk pilih, highlight ungu saat aktif).
  - Panel kanan: detail dokumen terpilih — header (No. Dok + status pill + tombol Print/Edit/Batal/Void), strip info (Customer/Project/Sales/Tanggal dst), tabel item, breakdown Subtotal/Diskon/PPN/Total.
- Form input (Buat Penawaran, Buat Invoice) = **halaman penuh terpisah**, BUKAN modal. Tombol "Batal" kembali ke list (browser-back-like).
- Status pill: solid berwarna (bg penuh + text warna kontras). Warna: Draft abu, Sent/Issued biru, Approved/Paid hijau, Rejected/Void merah, Cancelled/Menunggu kuning-oranye.
- Density tabel: compact.

## Modul Sudah Dibangun

### 1. Penawaran (List + Detail + Form)
- List: rail bulanan + detail (item table Produk/Tier/Qty/Harga/Total + Subtotal/Diskon/PPN 11%/Total). Aksi: Print, Edit, Batal, Void.
- Form "Buat Penawaran": Customer, Project (dropdown + "+ Tambah Project Baru" inline), Sales.
  - **Item row**: kombobox Produk (search-as-you-type, dengan opsi "+ Buat produk baru '...'" kalau ketik nama yang belum ada) → info box readonly di bawahnya nampilin spec produk (Material/Item/Collection/Ukuran + custom extra specs) → dropdown Tier/Quality (Ekonomis/Standard/Premium/dst, harga muncul cuma setelah tier dipilih) → Qty, Catatan (textarea).
  - Kolom Qty/Harga/Total fixed-width (70px/140px/130px) supaya gak geser-geser waktu tier dipilih.
  - Syarat & Ketentuan: dropdown pilih dari **master T&C**, preview isi singkat muncul di bawah (readonly).
  - Footer: Subtotal, Diskon (input value + toggle % / Rp), PPN 11% otomatis, Grand Total.

### 2. Produk & Tier (Master Data)
- Rail kiri: search produk, list produk (grouped by nama sama, spec beda — misal 3 varian "Kitchen Set Custom" material berbeda).
- Detail kanan: nama produk (editable inline), 4 kolom spec baku (Material/Item/Collection/Ukuran) + tombol "+ Tambah Kolom Spec" untuk atribut custom (label & value keduanya editable).
- Tier cards: **horizontal scroll row**, lebar fixed 260px/card (gak collapse/resize). Tiap card: nama tier, badge "🔒 Terpakai" kalau `used:true`, Harga Jual, BOM Komponen — tiap komponen berupa **kombobox Material** (search-as-you-type + "+ Buat material baru") dengan **materialId unik** terhubung ke master Material (`materialCatalog` — id, name, unit, defaultCost), Qty, Cost per komponen (card vertikal, bukan grid 3 kolom). Total Cost dihitung otomatis dari BOM, posisi footer di-pin di bawah card (`margin-top:auto`) biar gak geser.

### 3. Invoicing (List + Detail)
- Sama pola rail+detail. Kolom tambahan: badge status **Material** (klik → lompat ke Request Material terkait) — dihitung dari `requestStatus()`: "Terpenuhi dari Stok" / "Perlu PO" / "Menunggu PO" / "Siap Produksi".
- Detail: tabel item + Subtotal/Diskon/PPN/**Total Penawaran**, baris **Skema** (label DP kalau ada, misal "DP 50%"), lalu **Ditagihkan** (nominal aktual invoice ini, bisa beda dari Total Penawaran kalau DP/termin).
- Item invoice ditarik dari `quotationRef` yang link balik ke dokumen Penawaran sumbernya (`penawaranDocs`).

### 4. Buat Invoice (Form, full page)
- Pilih Project → keluar list Penawaran **status Approved** di project itu (radio card: No. Dok, Customer, Tanggal, Total, Termin T&C).
- Setelah pilih Penawaran → muncul preview T&C-nya (kalau ada) → pilih skema: **Lunas Sekaligus** atau **DP/Termin** (kalau DP: input jenis % atau Rp + nilainya).
- Ringkasan kanan bawah: Total Penawaran, baris DP/porsi, sisa termin berikutnya (kalau DP), **Ditagihkan** = nominal final.
- ⚠️ Tombol "Simpan Invoice" saat ini CUMA navigasi balik ke list — **belum** actually push ke `invoicingRows`. Perlu di-wire beneran di backend/logic produksi.

### 5. Request Material (List + Detail)
- Rail: filter bulan + list request (`MR-2026-xxx`), badge status per request (agregat dari semua baris semua produk).
- Detail: info strip (Customer, Project, No. Penawaran+tanggal, No. Invoice+tanggal, jumlah produk).
- **Multi-produk per invoice**: tiap produk di-render sebagai section terpisah (nama produk → spec box → tabel material sendiri) — bukan digabung flat.
- Tabel per produk: Material, Butuh, Stok, **Ambil dari Stok** (hijau), **Perlu PO** (merah), Status, tombol "+ Buat PO" (kalau shortage & belum ada PO).
- Klik "+ Buat PO" → **auto-create PO baru** (draft, vendor kosong, item ke-prefill dari qty kekurangan + projectId/requestId/invoiceRef ke-link) → langsung pindah ke layar PO detail, tinggal isi Vendor.
- Tombol "Lanjut ke Produksi (Buat SPK)" muncul di bawah, disabled sampai semua baris "Siap Produksi"/"Terpenuhi dari Stok".

### 6. Purchase Order (List + Detail)
- Rail: filter bulan + list PO (`PO-2026-xxx`), badge status (Draft/Ordered/Received).
- Detail: info strip (Customer, Project, Penawaran, Invoice, Request Material — full trail dari sumbernya), Vendor (dropdown dari master `vendors`), Tipe (pill, misal "Bahan Baku"), tabel item (Material, Qty, Cost/unit editable, Subtotal), Total, tombol "Kirim PO ke Vendor" (ubah status → Ordered).

## Data Model (mock, referensi struktur field)
```
projects: [{id, name}]
catalog (Produk): [{id, name, material, itemType, collection, size, extraSpecs:[{label,value}], tiers:[{id,name,price,used,bom:[{materialId,qty,cost}]}]}]
materialCatalog: [{id, name, unit, defaultCost}]
termsCatalog (T&C): [{id, title, preview}]
penawaranDocs: [{id, month, doc, customer, project, sales, status, date, items:[{produk,tier,qty,harga}], discountPercent}]
invoicingRows: [{doc, month, customer, sales, total, status, date, requestId, quotationRef, scheme, dpLabel}]
materialRequests: [{id, month, requestNo, invoiceRef, invoiceDate, quotationRef, quotationDate, customer, projectId, products:[{productLabel, productSpec, lines:[{materialName,unit,needQty,stockQty,poCreated}]}]}]
purchaseOrders: [{id, month, poNo, vendor, tipe, status, projectId, requestId, invoiceRef, items:[{materialName,unit,qty,cost}]}]
vendors: [string]
```

## BELUM Dikerjakan / Masih di Todo
1. **SPK Produksi, Penerimaan Barang, Delivery Order, Kuitansi, Kontak, Laporan, Dashboard, Master Data (Material list terpisah, Gudang), Admin (User, Roles)** — belum ada UI sama sekali, sesuai daftar menu sidebar. Perlu dibangun mengikuti `uploads/BACKOFFICE_UI_HANDOFF.md` (source-of-truth alur bisnis) + pola rail+detail yang sudah established di atas.
2. **Laporan per Project** — agregasi Penawaran/Invoice/PO yang linked by `projectId`, belum dibangun.
3. Tombol "Simpan Invoice" di form Buat Invoice belum actually menulis row baru ke `invoicingRows`.
4. Tombol "Lanjut ke Produksi (Buat SPK)" di Request Material belum wired ke modul SPK (karena SPK belum ada).
5. Print view (invoice/penawaran ke customer) belum dikerjakan — user bilang nanti aja fokus app dulu.

## Catatan Implementasi untuk Claude Code
- Semua ID (`productId`, `materialId`, `projectId`, `requestId`, `invoiceRef`, `quotationRef`, `poId`) di prototype ini string mock — di backend asli harus jadi foreign key antar tabel biar laporan per-project & audit-trail (Penawaran→Invoice→Request Material→PO) bisa di-query.
- Filter bulan di tiap modul pakai kunci `YYYY-MM` (lihat `monthInfo()` di logic class) — pastikan backend query invoice/PO/request per bulan pakai kolom tanggal asli, bukan string.
- Style pattern: inline styles, warna pakai OKLCH, radius ~7-12px, font Inter. Kalau dev pakai Tailwind/CSS-in-JS lain, styling ini jadi referensi visual token (spacing, radius, warna status) untuk di-port.
