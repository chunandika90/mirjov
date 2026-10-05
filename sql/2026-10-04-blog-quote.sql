-- Website Svashta — halaman detail Blog: tambah section kutipan.
-- Sesuai catatan Canva halaman 4: "kasih section quote (bisa jadi SEO juga)".
--
-- Kutipan sengaja jadi kolom sendiri, bukan diambil dari isi artikel, supaya:
--   1. bisa dipilih manual mana kalimat yang mau ditonjolkan,
--   2. bisa dipasang schema.org/Quotation buat SEO tanpa nebak-nebak isi tulisan.
--
-- Jalankan di database website (svashtahome_cms).

ALTER TABLE blog_posts
  ADD COLUMN quote VARCHAR(600) NULL AFTER content,
  ADD COLUMN quote_source VARCHAR(200) NULL AFTER quote;
