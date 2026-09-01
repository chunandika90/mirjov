-- Master data untuk karakteristik produk: Collection, Item Type, Finishing
-- (Material udah ada master table-nya sendiri: `materials`).
-- Dipakai sebagai sumber dropdown+search di form Produk, bisa di-manage
-- lewat halaman characteristics.php (add/edit/delete), dan auto-nambah kalau
-- user ngetik value baru pas nyimpen produk.

CREATE TABLE IF NOT EXISTS product_collections (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_org_collection (organization_id, name),
  CONSTRAINT fk_collection_org FOREIGN KEY (organization_id) REFERENCES organizations(id)
);

CREATE TABLE IF NOT EXISTS product_item_types (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_org_item_type (organization_id, name),
  CONSTRAINT fk_item_type_org FOREIGN KEY (organization_id) REFERENCES organizations(id)
);

CREATE TABLE IF NOT EXISTS product_finishings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_org_finishing (organization_id, name),
  CONSTRAINT fk_finishing_org FOREIGN KEY (organization_id) REFERENCES organizations(id)
);

ALTER TABLE products ADD COLUMN finishing VARCHAR(150) NULL AFTER size;
