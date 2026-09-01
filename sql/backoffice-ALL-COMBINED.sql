-- Wujud ERP — Backoffice Svashta Home — Fase 2 (backend real)
-- Database: svashtahome_svashta_procurement
-- Model: User (global identity) x Organization (tenant terisolasi) x Role (per-org)
-- lewat UserOrganizationRole. Lihat wujud-erp/DOKUMENTASI_ARSITEKTUR.md bagian 3.

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(30) NULL,
  password_hash VARCHAR(255) NOT NULL,
  photo VARCHAR(255) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE organizations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  legal_name VARCHAR(200) NOT NULL,
  npwp VARCHAR(30) NULL,
  address TEXT NULL,
  logo VARCHAR(255) NULL,
  currency VARCHAR(10) NOT NULL DEFAULT 'IDR',
  document_prefix VARCHAR(20) NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Role bawaan sistem (organization_id NULL) ATAU custom per organisasi.
CREATE TABLE roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NULL,
  name VARCHAR(100) NOT NULL,
  is_owner_role TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Matrix hak akses granular per role, per modul (module_key lihat backoffice-shared/modules.php).
CREATE TABLE role_module_access (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id INT UNSIGNED NOT NULL,
  module_key VARCHAR(50) NOT NULL,
  can_view TINYINT(1) NOT NULL DEFAULT 0,
  can_create TINYINT(1) NOT NULL DEFAULT 0,
  can_edit TINYINT(1) NOT NULL DEFAULT 0,
  can_delete TINYINT(1) NOT NULL DEFAULT 0,
  can_print TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_role_module (role_id, module_key)
) ENGINE=InnoDB;

-- Simpul keanggotaan: 1 baris = 1 user jadi anggota 1 organisasi dengan 1 role.
CREATE TABLE user_organization_roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  organization_id INT UNSIGNED NOT NULL,
  role_id INT UNSIGNED NOT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_membership (user_id, organization_id)
) ENGINE=InnoDB;
-- Wujud ERP — Backoffice Svashta Home — Fase 2, lanjutan skema modul transaksi.
-- Jalankan SETELAH backoffice-schema.sql (butuh tabel organizations, users, roles).
-- Database: svashtahome_svashta_procurement
--
-- Rantai dokumen: Penawaran -> Invoice -> (PO + SPK) -> Penerimaan Barang
--                  -> Delivery Order (minus stok) -> Kuitansi
-- Line-item provenance: tiap baris turunan bawa FK ke baris sumbernya
-- (lihat DOKUMENTASI_ARSITEKTUR.md bagian 1).

-- Nomor dokumen berurutan per organisasi, per jenis dokumen, per tahun.
CREATE TABLE doc_counters (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  doc_type VARCHAR(30) NOT NULL,
  year INT NOT NULL,
  last_number INT NOT NULL DEFAULT 0,
  UNIQUE KEY uniq_counter (organization_id, doc_type, year),
  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Kontak: customer & vendor, isolasi penuh per organisasi.
CREATE TABLE contacts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  type ENUM('customer','vendor','both') NOT NULL DEFAULT 'customer',
  name VARCHAR(200) NOT NULL,
  phone VARCHAR(30) NULL,
  email VARCHAR(150) NULL,
  address TEXT NULL,
  npwp VARCHAR(30) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Produk (mis. "Kitchen Set HPL", "Sofa Adhum").
CREATE TABLE products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  name VARCHAR(200) NOT NULL,
  category VARCHAR(100) NULL,
  unit VARCHAR(20) NOT NULL DEFAULT 'pcs',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5 Tier per produk = resep/kombinasi terkunci (bukan matrix atribut bebas).
-- Versioning: begitu tier dipakai di transaksi, versi lama TETAP ADA (immutable),
-- edit harga/BOM bikin `version` baru — dokumen lama tetap merujuk versi lama.
CREATE TABLE product_tiers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  tier_level ENUM('ekonomis','standard','premium','deluxe','bespoke') NOT NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  price DECIMAL(15,2) NOT NULL DEFAULT 0,
  bom_json JSON NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_tier_version (product_id, tier_level, version)
) ENGINE=InnoDB;

CREATE TABLE warehouses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Penawaran
CREATE TABLE quotations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  doc_number VARCHAR(40) NOT NULL,
  contact_id INT UNSIGNED NOT NULL,
  status ENUM('draft','sent','approved','rejected') NOT NULL DEFAULT 'draft',
  notes TEXT NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  FOREIGN KEY (contact_id) REFERENCES contacts(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  UNIQUE KEY uniq_doc (organization_id, doc_number)
) ENGINE=InnoDB;

CREATE TABLE quotation_lines (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quotation_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  product_name_snapshot VARCHAR(200) NOT NULL,
  tier_id INT UNSIGNED NOT NULL,
  tier_level_snapshot VARCHAR(20) NOT NULL,
  tier_version_snapshot INT UNSIGNED NOT NULL,
  bom_snapshot JSON NULL,
  qty DECIMAL(12,2) NOT NULL DEFAULT 1,
  unit_price DECIMAL(15,2) NOT NULL,
  custom_note TEXT NULL,
  FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (tier_id) REFERENCES product_tiers(id)
) ENGINE=InnoDB;

-- Invoice — bisa narik dari 1 atau beberapa Penawaran (per baris, bukan per header).
CREATE TABLE invoices (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  doc_number VARCHAR(40) NOT NULL,
  contact_id INT UNSIGNED NOT NULL,
  status ENUM('draft','issued','paid','void') NOT NULL DEFAULT 'draft',
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  FOREIGN KEY (contact_id) REFERENCES contacts(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  UNIQUE KEY uniq_doc (organization_id, doc_number)
) ENGINE=InnoDB;

CREATE TABLE invoice_lines (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT UNSIGNED NOT NULL,
  quotation_line_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  product_name_snapshot VARCHAR(200) NOT NULL,
  tier_level_snapshot VARCHAR(20) NOT NULL,
  qty DECIMAL(12,2) NOT NULL,
  unit_price DECIMAL(15,2) NOT NULL,
  FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
  FOREIGN KEY (quotation_line_id) REFERENCES quotation_lines(id),
  FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- Purchase Order: bahan baku / jasa produksi / barang jadi.
CREATE TABLE purchase_orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  doc_number VARCHAR(40) NOT NULL,
  vendor_id INT UNSIGNED NOT NULL,
  po_type ENUM('bahan_baku','jasa_produksi','barang_jadi') NOT NULL,
  status ENUM('draft','sent','partial','received','void') NOT NULL DEFAULT 'draft',
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  FOREIGN KEY (vendor_id) REFERENCES contacts(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  UNIQUE KEY uniq_doc (organization_id, doc_number)
) ENGINE=InnoDB;

CREATE TABLE po_lines (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  po_id INT UNSIGNED NOT NULL,
  invoice_line_id INT UNSIGNED NULL,
  product_id INT UNSIGNED NULL,
  item_name VARCHAR(200) NOT NULL,
  qty DECIMAL(12,2) NOT NULL,
  unit_cost DECIMAL(15,2) NOT NULL,
  received_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
  FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
  FOREIGN KEY (invoice_line_id) REFERENCES invoice_lines(id),
  FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- SPK Produksi — mendampingi PO jasa produksi.
CREATE TABLE spk (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  doc_number VARCHAR(40) NOT NULL,
  po_id INT UNSIGNED NOT NULL,
  vendor_id INT UNSIGNED NOT NULL,
  status ENUM('draft','in_production','done','void') NOT NULL DEFAULT 'draft',
  estimated_finish DATE NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  FOREIGN KEY (po_id) REFERENCES purchase_orders(id),
  FOREIGN KEY (vendor_id) REFERENCES contacts(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  UNIQUE KEY uniq_doc (organization_id, doc_number)
) ENGINE=InnoDB;

-- Penerimaan Barang — gudang ATAU langsung ke customer (skip gudang).
CREATE TABLE goods_receipts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  doc_number VARCHAR(40) NOT NULL,
  po_id INT UNSIGNED NOT NULL,
  warehouse_id INT UNSIGNED NULL,
  destination ENUM('warehouse','direct_customer') NOT NULL DEFAULT 'warehouse',
  received_by INT UNSIGNED NOT NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  FOREIGN KEY (po_id) REFERENCES purchase_orders(id),
  FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
  FOREIGN KEY (received_by) REFERENCES users(id),
  UNIQUE KEY uniq_doc (organization_id, doc_number)
) ENGINE=InnoDB;

CREATE TABLE goods_receipt_lines (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  goods_receipt_id INT UNSIGNED NOT NULL,
  po_line_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NULL,
  item_name VARCHAR(200) NOT NULL,
  qty DECIMAL(12,2) NOT NULL,
  unit_cost DECIMAL(15,2) NOT NULL,
  FOREIGN KEY (goods_receipt_id) REFERENCES goods_receipts(id) ON DELETE CASCADE,
  FOREIGN KEY (po_line_id) REFERENCES po_lines(id),
  FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- Buku besar stok — sumber kebenaran qty & cost per produk per gudang.
-- Metode costing: FIFO simplified (tiap baris OUT nyari baris IN yang belum
-- habis kepakai, tertua duluan) — dihitung di kode, bukan trigger DB.
CREATE TABLE stock_ledger (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  warehouse_id INT UNSIGNED NULL,
  product_id INT UNSIGNED NOT NULL,
  direction ENUM('in','out') NOT NULL,
  qty DECIMAL(12,2) NOT NULL,
  qty_remaining DECIMAL(12,2) NOT NULL DEFAULT 0,
  unit_cost DECIMAL(15,2) NOT NULL,
  ref_type VARCHAR(30) NOT NULL,
  ref_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
  FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- Delivery Order — pengiriman ke customer, MINUS stok (stock_ledger OUT).
CREATE TABLE delivery_orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  doc_number VARCHAR(40) NOT NULL,
  invoice_id INT UNSIGNED NOT NULL,
  contact_id INT UNSIGNED NOT NULL,
  warehouse_id INT UNSIGNED NULL,
  status ENUM('draft','shipped','delivered','void') NOT NULL DEFAULT 'draft',
  shipped_at DATETIME NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  FOREIGN KEY (invoice_id) REFERENCES invoices(id),
  FOREIGN KEY (contact_id) REFERENCES contacts(id),
  FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  UNIQUE KEY uniq_doc (organization_id, doc_number)
) ENGINE=InnoDB;

CREATE TABLE delivery_order_lines (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  delivery_order_id INT UNSIGNED NOT NULL,
  invoice_line_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  product_name_snapshot VARCHAR(200) NOT NULL,
  qty DECIMAL(12,2) NOT NULL,
  unit_cost_snapshot DECIMAL(15,2) NOT NULL DEFAULT 0,
  FOREIGN KEY (delivery_order_id) REFERENCES delivery_orders(id) ON DELETE CASCADE,
  FOREIGN KEY (invoice_line_id) REFERENCES invoice_lines(id),
  FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- Kuitansi & Pelunasan — berdasarkan status Delivery Order.
CREATE TABLE kuitansi (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  doc_number VARCHAR(40) NOT NULL,
  invoice_id INT UNSIGNED NOT NULL,
  delivery_order_id INT UNSIGNED NULL,
  amount DECIMAL(15,2) NOT NULL,
  payment_type ENUM('dp','termin','pelunasan') NOT NULL DEFAULT 'pelunasan',
  paid_at DATE NOT NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  FOREIGN KEY (invoice_id) REFERENCES invoices(id),
  FOREIGN KEY (delivery_order_id) REFERENCES delivery_orders(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  UNIQUE KEY uniq_doc (organization_id, doc_number)
) ENGINE=InnoDB;
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
-- Wujud ERP — Backoffice Svashta Home — Fase 2, lanjutan: atribusi Sales +
-- master Syarat & Ketentuan (T&C). Jalankan setelah semua migration sebelumnya.

ALTER TABLE quotations ADD COLUMN sales_user_id INT UNSIGNED NULL AFTER project_id;
ALTER TABLE quotations ADD CONSTRAINT fk_quotation_sales FOREIGN KEY (sales_user_id) REFERENCES users(id);

ALTER TABLE invoices ADD COLUMN sales_user_id INT UNSIGNED NULL AFTER contact_id;
ALTER TABLE invoices ADD CONSTRAINT fk_invoice_sales FOREIGN KEY (sales_user_id) REFERENCES users(id);

-- Master Syarat & Ketentuan — banyak varian (mis. 10 macam T&C beda produk/skema
-- pembayaran), dipilih pas bikin Penawaran/Invoice, teksnya di-snapshot ke dokumen
-- (bukan referensi hidup) biar T&C yang udah terbit gak berubah kalau master-nya diedit.
CREATE TABLE terms_conditions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  title VARCHAR(150) NOT NULL,
  content TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE quotations ADD COLUMN terms_snapshot TEXT NULL AFTER notes;
ALTER TABLE invoices ADD COLUMN terms_snapshot TEXT NULL AFTER sales_user_id;
