-- Tambah field SEO opsional (title/description) ke 3 tabel konten utama.
-- Jalankan lewat phpMyAdmin > Import di database svashtahome_cms.
-- Aman dijalankan sekali — kalau kolomnya udah ada, tinggal skip baris yang error "Duplicate column".

ALTER TABLE products ADD COLUMN seo_title VARCHAR(255) NULL AFTER description;
ALTER TABLE products ADD COLUMN seo_description VARCHAR(300) NULL AFTER seo_title;

ALTER TABLE blog_posts ADD COLUMN seo_title VARCHAR(255) NULL AFTER content;
ALTER TABLE blog_posts ADD COLUMN seo_description VARCHAR(300) NULL AFTER seo_title;

ALTER TABLE projects ADD COLUMN seo_title VARCHAR(255) NULL AFTER story;
ALTER TABLE projects ADD COLUMN seo_description VARCHAR(300) NULL AFTER seo_title;
