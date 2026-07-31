-- Wujud ERP — Backoffice Svashta Home — Fase 2, lanjutan: atribusi Sales +
-- master Syarat & Ketentuan (T&C). Jalankan setelah semua migration sebelumnya.

ALTER TABLE quotations ADD COLUMN sales_user_id INT UNSIGNED NULL AFTER project_id;
ALTER TABLE quotations ADD CONSTRAINT fk_quotation_sales FOREIGN KEY (sales_user_id) REFERENCES users(id);

ALTER TABLE invoices ADD COLUMN sales_user_id INT UNSIGNED NULL AFTER contact_id;
ALTER TABLE invoices ADD CONSTRAINT fk_invoice_sales FOREIGN KEY (sales_user_id) REFERENCES users(id);

-- Master Syarat & Ketentuan — banyak varian (mis. 10 macam T&C beda produk/skema
-- pembayaran), dipilih pas bikin Penawaran/Invoice, teksnya di-snapshot ke dokumen
-- (bukan referensi hidup) biar T&C yang udah terbit gak berubah kalau master-nya diedit.
CREATE TABLE terms_conditions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  title VARCHAR(150) NOT NULL,
  content TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE quotations ADD COLUMN terms_snapshot TEXT NULL AFTER notes;
ALTER TABLE invoices ADD COLUMN terms_snapshot TEXT NULL AFTER sales_user_id;
