-- Tambah kolom photo_path ke tabel reviews — beda dari avatar_path (foto profil
-- kecil di sebelah nama), photo_path itu foto besar yang tampil side-by-side
-- sama teks quote-nya di homepage.
-- Jalankan lewat phpMyAdmin > Import di database svashtahome_cms.

ALTER TABLE reviews ADD COLUMN photo_path VARCHAR(255) NULL AFTER avatar_path;
