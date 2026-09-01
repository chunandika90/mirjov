-- Audit trail: siapa yang bikin (created_by) dan siapa yang terakhir edit
-- (updated_by) tiap baris konten CMS, plus updated_at di tabel yang belum punya.
-- Jalankan lewat phpMyAdmin (Import) di database svashtahome_cms.

ALTER TABLE hero_slides
  ADD COLUMN created_by INT UNSIGNED NULL AFTER created_at,
  ADD COLUMN updated_by INT UNSIGNED NULL AFTER updated_at,
  ADD CONSTRAINT fk_hero_created_by FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_hero_updated_by FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL;

ALTER TABLE collaborators
  ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
  ADD COLUMN created_by INT UNSIGNED NULL AFTER updated_at,
  ADD COLUMN updated_by INT UNSIGNED NULL AFTER created_by,
  ADD CONSTRAINT fk_collab_created_by FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_collab_updated_by FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL;

ALTER TABLE reviews
  ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
  ADD COLUMN created_by INT UNSIGNED NULL AFTER updated_at,
  ADD COLUMN updated_by INT UNSIGNED NULL AFTER created_by,
  ADD CONSTRAINT fk_review_created_by FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_review_updated_by FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL;

ALTER TABLE partner_logos
  ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
  ADD COLUMN created_by INT UNSIGNED NULL AFTER updated_at,
  ADD COLUMN updated_by INT UNSIGNED NULL AFTER created_by,
  ADD CONSTRAINT fk_partner_created_by FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_partner_updated_by FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL;

ALTER TABLE homepage_backgrounds
  ADD COLUMN updated_by INT UNSIGNED NULL AFTER updated_at,
  ADD CONSTRAINT fk_bg_updated_by FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL;

ALTER TABLE homepage_video
  ADD COLUMN updated_by INT UNSIGNED NULL AFTER updated_at,
  ADD CONSTRAINT fk_video_updated_by FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL;

ALTER TABLE products
  ADD COLUMN created_by INT UNSIGNED NULL AFTER created_at,
  ADD COLUMN updated_by INT UNSIGNED NULL AFTER updated_at,
  ADD CONSTRAINT fk_product_created_by FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_product_updated_by FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL;

ALTER TABLE projects
  ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
  ADD COLUMN created_by INT UNSIGNED NULL AFTER updated_at,
  ADD COLUMN updated_by INT UNSIGNED NULL AFTER created_by,
  ADD CONSTRAINT fk_project_created_by FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_project_updated_by FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL;

ALTER TABLE blog_posts
  ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
  ADD COLUMN created_by INT UNSIGNED NULL AFTER updated_at,
  ADD COLUMN updated_by INT UNSIGNED NULL AFTER created_by,
  ADD CONSTRAINT fk_blog_created_by FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_blog_updated_by FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL;

-- custom_orders: bukan konten yang di-"create" admin (masuk dari form publik),
-- tapi tetep dilacak siapa admin yang balas/update status-nya.
ALTER TABLE custom_orders
  ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
  ADD COLUMN updated_by INT UNSIGNED NULL AFTER updated_at,
  ADD CONSTRAINT fk_order_updated_by FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL;
