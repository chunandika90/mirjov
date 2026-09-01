-- Tambah tabel buat background section homepage yang bisa diedit (mulai dari Review).
-- Jalankan lewat phpMyAdmin > Import di database svashtahome_cms.

CREATE TABLE IF NOT EXISTS homepage_backgrounds (
  id TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  review_bg_image VARCHAR(255) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_single_row_bg CHECK (id = 1)
) ENGINE=InnoDB;

INSERT IGNORE INTO homepage_backgrounds (id) VALUES (1);
