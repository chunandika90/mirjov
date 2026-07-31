-- PIC (penanggung jawab internal) + Lead Sales per Project — biar keliatan
-- sebelum Penawaran dibuat. Keduanya nunjuk ke users (org members yang sama
-- dipakai sebagai master "Sales" di Penawaran/Invoice, gak bikin tabel baru).
ALTER TABLE projects
  ADD COLUMN pic_user_id INT UNSIGNED NULL AFTER contact_id,
  ADD COLUMN sales_user_id INT UNSIGNED NULL AFTER pic_user_id,
  ADD CONSTRAINT fk_project_pic FOREIGN KEY (pic_user_id) REFERENCES users(id),
  ADD CONSTRAINT fk_project_sales FOREIGN KEY (sales_user_id) REFERENCES users(id);
