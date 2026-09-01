-- Wujud ERP — Backoffice Svashta Home — Fase 2, v3: hasil redesign Claude Design
-- (lihat HANDOFF_PENAWARAN_UI.md). Jalankan setelah semua migration sebelumnya
-- (backoffice-schema.sql, -modules.sql, -manufacturing.sql, -sales-terms.sql).

-- Produk sekarang punya spec baku (Material/Item/Collection/Ukuran) + custom spec bebas,
-- dan varian (nama sama, spec beda) tinggal jadi baris `products` terpisah, di-grup di UI.
ALTER TABLE products ADD COLUMN material VARCHAR(150) NULL AFTER category;
ALTER TABLE products ADD COLUMN item_type VARCHAR(150) NULL AFTER material;
ALTER TABLE products ADD COLUMN collection VARCHAR(150) NULL AFTER item_type;
ALTER TABLE products ADD COLUMN size VARCHAR(150) NULL AFTER collection;
ALTER TABLE products ADD COLUMN extra_specs JSON NULL AFTER size;

-- Cost default material (buat prefill pas dipakai di BOM tier / PO / Request Material).
ALTER TABLE materials ADD COLUMN default_cost DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER unit;

-- CATATAN PENTING: mulai versi ini, `product_tiers.bom_json` isinya
-- [{material_id, material_name, qty, cost}, ...] — WAJIB pakai material_id yang valid
-- (bukan lagi teks bebas `component`), biar Request Material bisa hitung kebutuhan
-- per material vs stok. Tier lama (bom_json format lama tanpa material_id) tetap
-- kebaca buat ditampilkan di dokumen historis, tapi gak bisa dipakai hitung Request
-- Material — kalau ada tier lama yang masih dipakai transaksi aktif, edit ulang BOM-nya
-- lewat halaman Produk & Tier biar ke-link ke Material yang bener.

-- Diskon di Penawaran (PPN 11% dihitung otomatis saat render/print, gak perlu disimpan).
ALTER TABLE quotations ADD COLUMN discount_type ENUM('percent','amount') NULL AFTER notes;
ALTER TABLE quotations ADD COLUMN discount_value DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER discount_type;

-- Skema pembayaran Invoice: Lunas Sekaligus atau DP/Termin. billed_amount = nominal yang
-- BENERAN ditagihkan di invoice ini (snapshot, bukan dihitung ulang tiap render, biar gak
-- geser kalau skema/termin berubah di masa depan).
ALTER TABLE invoices ADD COLUMN payment_scheme ENUM('full','dp') NOT NULL DEFAULT 'full' AFTER sales_user_id;
ALTER TABLE invoices ADD COLUMN dp_type ENUM('percent','amount') NULL AFTER payment_scheme;
ALTER TABLE invoices ADD COLUMN dp_value DECIMAL(15,2) NULL AFTER dp_type;
ALTER TABLE invoices ADD COLUMN billed_amount DECIMAL(15,2) NULL AFTER dp_value;

-- Invoice sekarang dibuat dari 1 Penawaran approved yang utuh (bukan cherry-pick baris
-- lintas dokumen) — simpan referensinya biar gampang query balik ("Invoice ini dari
-- Penawaran mana").
ALTER TABLE invoices ADD COLUMN quotation_id INT UNSIGNED NULL AFTER contact_id;
ALTER TABLE invoices ADD CONSTRAINT fk_invoice_quotation FOREIGN KEY (quotation_id) REFERENCES quotations(id);

-- Request Material — jembatan Invoice -> Purchase Order. 1 request = 1 invoice, isinya
-- kebutuhan material tiap produk di invoice itu (dihitung dari BOM tier x qty),
-- dibandingkan sama stok yang ada, dipecah jadi "ambil dari stok" vs "perlu PO".
CREATE TABLE material_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  doc_number VARCHAR(40) NOT NULL,
  invoice_id INT UNSIGNED NOT NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  FOREIGN KEY (invoice_id) REFERENCES invoices(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  UNIQUE KEY uniq_doc (organization_id, doc_number),
  UNIQUE KEY uniq_invoice (invoice_id)
) ENGINE=InnoDB;

CREATE TABLE material_request_lines (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id INT UNSIGNED NOT NULL,
  invoice_line_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NULL,
  product_name_snapshot VARCHAR(200) NOT NULL,
  material_id INT UNSIGNED NOT NULL,
  material_name_snapshot VARCHAR(200) NOT NULL,
  unit VARCHAR(20) NOT NULL DEFAULT 'pcs',
  need_qty DECIMAL(12,2) NOT NULL,
  stock_qty_snapshot DECIMAL(12,2) NOT NULL DEFAULT 0,
  take_from_stock_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
  need_po_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
  po_line_id INT UNSIGNED NULL,
  FOREIGN KEY (request_id) REFERENCES material_requests(id) ON DELETE CASCADE,
  FOREIGN KEY (invoice_line_id) REFERENCES invoice_lines(id),
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (material_id) REFERENCES materials(id),
  FOREIGN KEY (po_line_id) REFERENCES po_lines(id)
) ENGINE=InnoDB;

-- Purchase Order sekarang bisa dilacak balik ke Project & Request Material asalnya
-- (auto-created dari kekurangan stok), plus vendor boleh NULL dulu (diisi belakangan).
ALTER TABLE purchase_orders MODIFY COLUMN vendor_id INT UNSIGNED NULL;
ALTER TABLE purchase_orders ADD COLUMN project_id INT UNSIGNED NULL AFTER organization_id;
ALTER TABLE purchase_orders ADD COLUMN material_request_id INT UNSIGNED NULL AFTER project_id;
ALTER TABLE purchase_orders ADD CONSTRAINT fk_po_project FOREIGN KEY (project_id) REFERENCES projects(id);
ALTER TABLE purchase_orders ADD CONSTRAINT fk_po_request FOREIGN KEY (material_request_id) REFERENCES material_requests(id);

-- goods_receipt_lines.po_line_id juga harus nullable — Penerimaan dari SPK (hasil
-- produksi/rakit) gak punya po_line_id sama sekali (lupa ke-apply pas nambah kolom
-- spk_id di goods_receipts sebelumnya, ini nyusulin).
ALTER TABLE goods_receipt_lines MODIFY COLUMN po_line_id INT UNSIGNED NULL;
