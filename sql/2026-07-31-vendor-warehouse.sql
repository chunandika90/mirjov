-- Gudang milik vendor (virtual location) — dipake pas barang dari PO sengaja
-- disimpan/dipegang di lokasi vendor, bukan gudang Svashta sendiri. Bikin
-- stok tetap ke-track (buat SPK & laporan), cuma beda lokasi fisiknya.

ALTER TABLE warehouses
  ADD COLUMN vendor_id INT UNSIGNED NULL AFTER name,
  ADD CONSTRAINT fk_warehouse_vendor FOREIGN KEY (vendor_id) REFERENCES contacts(id);

-- Nandain PO ini barangnya dituju ke gudang vendor (dicentang pas bikin/assign vendor),
-- dipake buat auto pre-select gudang tujuan pas Catat Penerimaan.
ALTER TABLE purchase_orders
  ADD COLUMN destination_warehouse_id INT UNSIGNED NULL AFTER vendor_id,
  ADD CONSTRAINT fk_po_destination_warehouse FOREIGN KEY (destination_warehouse_id) REFERENCES warehouses(id);
