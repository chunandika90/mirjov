-- Website Svashta — kumpulan perubahan database untuk permintaan di Canva.
-- Aman dijalankan berulang: semua pakai IF NOT EXISTS / ON DUPLICATE KEY.
-- Jalankan di database website (svashtahome_cms).
--
-- Mencakup:
--   1. homepage_hero   — teks hero jadi satu setelan global (catatan #1 & #2)
--   2. about_photos    — foto "What Defines" bisa ganti-ganti, maks 4 (catatan #3)
--   3. products.thumb_image — foto sampul daftar beda dari foto di dalam (catatan #13 & #14)


-- ============================================================
-- 1. Teks hero (catatan #1 & #2)
-- Sebelumnya judul/subjudul ada di tiap baris hero_slides sehingga ikut berganti
-- tiap slide. Sekarang satu baris global; hero_slides tinggal menyimpan fotonya.
-- Kolom title/subtitle di hero_slides sengaja tidak dihapus — datanya dibiarkan.
-- ============================================================
CREATE TABLE IF NOT EXISTS homepage_hero (
  id TINYINT UNSIGNED NOT NULL DEFAULT 1 PRIMARY KEY,
  title VARCHAR(255) NOT NULL DEFAULT '',
  subtitle VARCHAR(500) NOT NULL DEFAULT '',
  cta_label VARCHAR(80) NOT NULL DEFAULT '',
  cta_link VARCHAR(255) NOT NULL DEFAULT '#about-us',
  updated_at DATETIME NULL,
  updated_by INT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO homepage_hero (id, title, subtitle, cta_label, cta_link)
VALUES (1,
        'SVASHTA HOME - The Architect of Comfort',
        'A bespoke fine furnishing company, pride of Indonesia',
        'Know Us Better',
        '#about-us')
ON DUPLICATE KEY UPDATE id = id;


-- ============================================================
-- 2. Foto section "What Defines Svashta Home" (catatan #3)
-- Dulu satu foto statis. Sekarang daftar foto yang berganti otomatis.
-- Kalau tabel ini kosong, halaman otomatis kembali memakai foto statis lama,
-- jadi tidak pernah tampil kosong.
-- ============================================================
CREATE TABLE IF NOT EXISTS about_photos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  image_path VARCHAR(255) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  updated_at DATETIME NULL,
  updated_by INT UNSIGNED NULL,
  KEY idx_about_photos_urut (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Isi awal memakai foto yang sudah ada di folder assets situs.
INSERT INTO about_photos (id, image_path, sort_order) VALUES
  (1, 'https://svashtahome.com/assets/img/about_us_new.jpg', 0),
  (2, 'https://svashtahome.com/assets/img/about-us-5.jpg',   1),
  (3, 'https://svashtahome.com/assets/img/interior-1.jpg',   2),
  (4, 'https://svashtahome.com/assets/img/interior-4.jpg',   3)
ON DUPLICATE KEY UPDATE id = id;


-- ============================================================
-- 3. Foto sampul produk terpisah (catatan #13 & #14)
-- "mau bikin cover thumbnail berbeda dengan isinya (saat di click)" —
-- jadi daftar produk memakai thumb_image, sedangkan cover_image tetap dipakai
-- di halaman detail. Kalau thumb_image kosong, daftar jatuh kembali ke
-- cover_image, sehingga produk lama tetap tampil seperti sekarang.
-- ============================================================
-- IF NOT EXISTS supaya file ini aman dijalankan ulang (MariaDB mendukungnya).
ALTER TABLE products
  ADD COLUMN IF NOT EXISTS thumb_image VARCHAR(255) NULL AFTER cover_image;


-- ============================================================
-- 4. Section koleksi unggulan di homepage (catatan #4, #5, #6)
-- "Page mau bisa di click langsung ke content collection Paradaghda",
-- "All text mau bisa di edit agar bisa diganti sesuai koleksi yg lagi mau di up",
-- "Halaman ini mau ada 2 foto yang bisa diganti kanan kiri".
--
-- Semua teks DAN kedua fotonya disimpan di sini supaya bisa diganti dari CMS
-- tiap kali koleksi yang ditonjolkan berganti — tanpa menyentuh kode.
-- cta_link dibiarkan bebas diisi: arahkan ke halaman koleksi begitu produknya siap.
-- ============================================================
CREATE TABLE IF NOT EXISTS featured_collection (
  id TINYINT UNSIGNED NOT NULL DEFAULT 1 PRIMARY KEY,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  eyebrow VARCHAR(120) NOT NULL DEFAULT '',
  title VARCHAR(255) NOT NULL DEFAULT '',
  meta VARCHAR(120) NOT NULL DEFAULT '',
  cta_label VARCHAR(80) NOT NULL DEFAULT '',
  cta_link VARCHAR(255) NOT NULL DEFAULT '',
  image_left VARCHAR(255) NOT NULL DEFAULT '',
  image_right VARCHAR(255) NOT NULL DEFAULT '',
  updated_at DATETIME NULL,
  updated_by INT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO featured_collection (id, aktif, eyebrow, title, meta, cta_label, cta_link, image_left, image_right)
VALUES (1, 1,
        'PARADAGHDA COLLECTION',
        'The Heritage of Fire & Preservation',
        'Svashta Home | 2026',
        'Discover more',
        '/product',
        'https://svashtahome.com/assets/img/interior-6.jpg',
        'https://svashtahome.com/assets/img/about-us-5.jpg')
ON DUPLICATE KEY UPDATE id = id;
