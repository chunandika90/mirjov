-- Custom Order -> "Consultation": wording halaman + 2 dropdown yang bisa diatur
-- dari CMS, plus kolom baru di custom_orders buat nampung field form yang baru.

CREATE TABLE consultation_page (
  id INT UNSIGNED PRIMARY KEY DEFAULT 1,
  eyebrow_text VARCHAR(255) NOT NULL DEFAULT 'SVASHTA HOME BESPOKE FINE FURNISHINGS',
  subtitle_text VARCHAR(255) NOT NULL DEFAULT 'I WANT TO BOOK FOR',
  title_text VARCHAR(255) NOT NULL DEFAULT 'A Consultation',
  updated_by INT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
INSERT INTO consultation_page (id) VALUES (1);

CREATE TABLE consultation_need_options (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  label VARCHAR(150) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;
INSERT INTO consultation_need_options (label, sort_order) VALUES
  ('Sofa', 1), ('Table', 2), ('Chair', 3), ('Others', 4);

CREATE TABLE consultation_for_options (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  label VARCHAR(150) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;
INSERT INTO consultation_for_options (label, sort_order) VALUES
  ('My Personal Use', 1), ('My Project (I am a Professional)', 2), ('Others', 3);

ALTER TABLE custom_orders
  ADD COLUMN location VARCHAR(255) NULL AFTER customer_name,
  ADD COLUMN need_category VARCHAR(150) NULL AFTER location,
  ADD COLUMN need_for VARCHAR(150) NULL AFTER need_category;
