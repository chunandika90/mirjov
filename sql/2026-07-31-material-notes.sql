-- Notes bebas di Material, sama kayak pattern di Produk — buat nyatet asal-usul
-- (misal "dibikin pas project Butik Hotel Ubud") biar gampang ditelusur pas mau
-- repeat order atau copy spec ke penawaran lain.
ALTER TABLE materials ADD COLUMN notes TEXT NULL AFTER default_cost;
