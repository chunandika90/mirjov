-- Foto produk (1 foto utama per produk), buat ditampilin di print Penawaran/dokumen
-- lain per baris barang. Disimpen resized (bukan file asli) biar hemat storage.
ALTER TABLE products ADD COLUMN photo_path VARCHAR(255) NULL AFTER finishing;
