-- Svashta Home CMS — Database Schema
-- Jalankan lewat phpMyAdmin (Import) di database yang sama dipakai
-- svashtahome.com dan subdomain cms.svashtahome.com.

CREATE DATABASE IF NOT EXISTS svashtahome_cms
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE svashtahome_cms;

-- ============================================================
-- Auth
-- ============================================================
CREATE TABLE admin_users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(60) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- Homepage — modul yang dibangun duluan
-- ============================================================
CREATE TABLE hero_slides (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  subtitle TEXT NULL,
  image_path VARCHAR(255) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Single-row settings table buat background foto section-section homepage yang
-- awalnya hardcode di tema (mulai dari Review, bisa ditambah kolom lagi nanti
-- kalau section lain juga mau bisa diganti backgroundnya lewat CMS).
CREATE TABLE homepage_backgrounds (
  id TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  review_bg_image VARCHAR(255) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_single_row_bg CHECK (id = 1)
) ENGINE=InnoDB;
INSERT INTO homepage_backgrounds (id) VALUES (1);

-- Single-row settings table untuk section "Watch our video"
CREATE TABLE homepage_video (
  id TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  headline VARCHAR(150) NOT NULL DEFAULT '',
  slogan VARCHAR(150) NOT NULL DEFAULT '',
  video_path VARCHAR(255) NULL,
  youtube_id VARCHAR(50) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_single_row CHECK (id = 1)
) ENGINE=InnoDB;
INSERT INTO homepage_video (id, headline, slogan) VALUES (1, 'Watch our', 'video');

CREATE TABLE collaborators (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  link_url VARCHAR(255) NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE reviews (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  avatar_path VARCHAR(255) NULL,
  photo_path VARCHAR(255) NULL,
  quote TEXT NOT NULL,
  rating TINYINT UNSIGNED NOT NULL DEFAULT 5 CHECK (rating BETWEEN 1 AND 5),
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE review_photos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  review_id INT UNSIGNED NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE partner_logos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  image_path VARCHAR(255) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- Fase berikutnya (tabel disiapkan sekarang, UI admin belum dibangun) —
-- Products, Projects, Blog, Custom Orders. Jangan dihapus, tinggal
-- dibangun modul admin-nya belakangan tanpa perlu ubah skema.
-- ============================================================
CREATE TABLE products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(170) NOT NULL UNIQUE,
  category ENUM('sofa','table','chair','bed','cabinet','outdoor','collections','collaborations') NOT NULL,
  price DECIMAL(14,2) NULL,
  materials VARCHAR(255) NULL,
  description TEXT NULL,
  seo_title VARCHAR(255) NULL,
  seo_description VARCHAR(300) NULL,
  cover_image VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE product_gallery (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE product_highlights (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  label VARCHAR(100) NOT NULL,
  text TEXT NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE projects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(170) NOT NULL UNIQUE,
  location VARCHAR(150) NULL,
  excerpt VARCHAR(300) NULL,
  collection VARCHAR(100) NULL,
  story TEXT NULL,
  seo_title VARCHAR(255) NULL,
  seo_description VARCHAR(300) NULL,
  cover_image VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE project_gallery (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE blog_posts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  slug VARCHAR(220) NOT NULL UNIQUE,
  excerpt VARCHAR(300) NULL,
  content MEDIUMTEXT NULL,
  seo_title VARCHAR(255) NULL,
  seo_description VARCHAR(300) NULL,
  cover_image VARCHAR(255) NULL,
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE blog_gallery (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  blog_post_id INT UNSIGNED NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  FOREIGN KEY (blog_post_id) REFERENCES blog_posts(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE custom_orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_name VARCHAR(150) NOT NULL,
  contact VARCHAR(150) NOT NULL,
  request TEXT NOT NULL,
  admin_reply TEXT NULL,
  replied_at DATETIME NULL,
  status ENUM('pending','processed','void') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
