-- Tambah field excerpt (deskripsi pendek) buat card di halaman Projects list.
-- Beda sama `story` (teks panjang di halaman detail) — excerpt ini yang muncul
-- di bawah judul pas listing/grid Projects.
ALTER TABLE projects ADD COLUMN excerpt VARCHAR(300) NULL AFTER location;
