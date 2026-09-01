-- Wujud ERP — Backoffice Svashta Home — Fase 2, lanjutan: Manufaktur/Rakit Barang + Project.
-- Jalankan SETELAH backoffice-schema.sql DAN backoffice-schema-modules.sql.

-- Katalog bahan/material mentah (BEDA dari `products` yang isinya barang jadi 5-tier).
-- Material yang masuk lewat PO bahan_baku + Penerimaan jadi trackable stok-nya (stock_ledger).
CREATE TABLE materials (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  name VARCHAR(200) NOT NULL,
  unit VARCHAR(20) NOT NULL DEFAULT 'pcs',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Project: payung buat banyak Penawaran (periode beda-beda) yang sebenarnya 1 pekerjaan/klien.
CREATE TABLE projects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  name VARCHAR(200) NOT NULL,
  contact_id INT UNSIGNED NULL,
  notes TEXT NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  FOREIGN KEY (contact_id) REFERENCES contacts(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

ALTER TABLE quotations ADD COLUMN project_id INT UNSIGNED NULL AFTER contact_id;
ALTER TABLE quotations ADD CONSTRAINT fk_quotation_project FOREIGN KEY (project_id) REFERENCES projects(id);

-- stock_ledger sekarang bisa nyimpen 2 jenis barang: produk jadi (product_id) ATAU
-- material mentah (material_id) — persis 1 dari 2 kolom ini yang keisi per baris (app-enforced).
ALTER TABLE stock_ledger MODIFY COLUMN product_id INT UNSIGNED NULL;
ALTER TABLE stock_ledger ADD COLUMN material_id INT UNSIGNED NULL AFTER product_id;
ALTER TABLE stock_ledger ADD CONSTRAINT fk_ledger_material FOREIGN KEY (material_id) REFERENCES materials(id);

-- PO bahan_baku sekarang bisa link ke Material katalog (biar trackable stoknya),
-- selain tetap boleh item bebas (material_id NULL = item sekali pakai, gak ditrack).
ALTER TABLE po_lines ADD COLUMN material_id INT UNSIGNED NULL AFTER product_id;
ALTER TABLE po_lines ADD CONSTRAINT fk_poline_material FOREIGN KEY (material_id) REFERENCES materials(id);

-- goods_receipt_lines ikut bisa nyimpen material_id (mengikuti po_lines sumbernya).
ALTER TABLE goods_receipt_lines ADD COLUMN material_id INT UNSIGNED NULL AFTER product_id;
ALTER TABLE goods_receipt_lines ADD CONSTRAINT fk_grline_material FOREIGN KEY (material_id) REFERENCES materials(id);

-- SPK sekarang jadi Manufacturing Order beneran: target produk jadi + qty hasil + fee jasa
-- rakit. po_id jadi opsional (SPK bisa dibuat langsung tanpa PO formal kalau fee-nya manual).
ALTER TABLE spk MODIFY COLUMN po_id INT UNSIGNED NULL;
ALTER TABLE spk ADD COLUMN product_id INT UNSIGNED NULL AFTER po_id;
ALTER TABLE spk ADD COLUMN output_qty DECIMAL(12,2) NULL;
ALTER TABLE spk ADD COLUMN assembly_fee DECIMAL(15,2) NOT NULL DEFAULT 0;
ALTER TABLE spk ADD CONSTRAINT fk_spk_product FOREIGN KEY (product_id) REFERENCES products(id);

-- Baris material yang DIKIRIM ke vendor perakit buat 1 SPK — qty diminus dari stok material
-- (FIFO) saat SPK dibuat, unit_cost snapshot dari situ.
CREATE TABLE spk_materials (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  spk_id INT UNSIGNED NOT NULL,
  material_id INT UNSIGNED NOT NULL,
  material_name_snapshot VARCHAR(200) NOT NULL,
  qty DECIMAL(12,2) NOT NULL,
  unit_cost DECIMAL(15,2) NOT NULL DEFAULT 0,
  FOREIGN KEY (spk_id) REFERENCES spk(id) ON DELETE CASCADE,
  FOREIGN KEY (material_id) REFERENCES materials(id)
) ENGINE=InnoDB;

-- goods_receipts sekarang bisa berasal dari PO (beli dari luar) ATAU dari SPK
-- (hasil produksi/rakit selesai) — persis 1 dari 2 yang keisi.
ALTER TABLE goods_receipts MODIFY COLUMN po_id INT UNSIGNED NULL;
ALTER TABLE goods_receipts ADD COLUMN spk_id INT UNSIGNED NULL AFTER po_id;
ALTER TABLE goods_receipts ADD CONSTRAINT fk_receipt_spk FOREIGN KEY (spk_id) REFERENCES spk(id);
