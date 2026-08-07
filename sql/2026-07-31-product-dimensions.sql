-- Dimensi ukuran produk (dalam mm) — dipisah dari field "Ukuran" bebas yang lama,
-- biar numerik & konsisten buat ditampilin rapi per tipe furniture.
ALTER TABLE products
  ADD COLUMN panjang DECIMAL(8,2) NULL AFTER size,
  ADD COLUMN lebar DECIMAL(8,2) NULL AFTER panjang,
  ADD COLUMN tinggi DECIMAL(8,2) NULL AFTER lebar,
  ADD COLUMN tinggi_dudukan DECIMAL(8,2) NULL AFTER tinggi,
  ADD COLUMN tinggi_lengan DECIMAL(8,2) NULL AFTER tinggi_dudukan,
  ADD COLUMN tinggi_sandaran DECIMAL(8,2) NULL AFTER tinggi_lengan,
  ADD COLUMN tinggi_kaki DECIMAL(8,2) NULL AFTER tinggi_sandaran;
