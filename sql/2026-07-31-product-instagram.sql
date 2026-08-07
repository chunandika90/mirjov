-- Section Instagram di halaman detail produk (di bawah carousel foto) — bisa
-- nambahin lebih dari 1 post per produk, dikelola dari CMS Products.
CREATE TABLE product_instagram_posts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  instagram_url VARCHAR(500) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;
