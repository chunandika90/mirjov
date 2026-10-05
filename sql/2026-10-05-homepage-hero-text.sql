-- Website Svashta — hero homepage: teks dipisah dari slide.
-- Sesuai catatan Canva halaman 1: "Mau dibuat gambar bisa diganti, dan TEXT stay
-- aja didepan (gambarnya yang ganti-ganti)".
--
-- Sebelumnya tiap baris hero_slides punya judul + subjudul sendiri, jadi teksnya
-- ikut berganti tiap slide. Sekarang teks jadi SATU setelan global (tabel 1 baris,
-- pola yang sama dengan homepage_video dan homepage_backgrounds), dan hero_slides
-- tinggal dipakai buat fotonya saja.
--
-- Kolom title/subtitle di hero_slides sengaja TIDAK dihapus — datanya dibiarkan utuh
-- supaya kalau nanti berubah pikiran, teks lama masih ada.
--
-- Jalankan di database website (svashtahome_cms).

CREATE TABLE IF NOT EXISTS homepage_hero (
  id TINYINT UNSIGNED NOT NULL DEFAULT 1 PRIMARY KEY,
  title VARCHAR(255) NOT NULL DEFAULT '',
  subtitle VARCHAR(500) NOT NULL DEFAULT '',
  cta_label VARCHAR(80) NOT NULL DEFAULT '',
  cta_link VARCHAR(255) NOT NULL DEFAULT '#about-us',
  updated_at DATETIME NULL,
  updated_by INT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Isi awal persis seperti mockup Canva.
INSERT INTO homepage_hero (id, title, subtitle, cta_label, cta_link)
VALUES (1,
        'SVASHTA HOME - The Architect of Comfort',
        'A bespoke fine furnishing company, pride of Indonesia',
        'Know Us Better',
        '#about-us')
ON DUPLICATE KEY UPDATE id = id;
