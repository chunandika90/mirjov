-- Manufaktur — Form Penawaran Harga: Approval MJ, upload Form QC, dan Tagihan DP/Pelunasan.
-- Sesuai brief PPT "ANDIKA FORM PERMINTAAN PENAWARAN HARGA-DST" (slide 3).
--
-- Alurnya: MJ isi PPH -> MMT isi harga -> MJ KLIK SETUJU -> MMT buat Tagihan DP ->
-- barang jadi, MMT upload 5 Form QC + foto hasil -> baru Tagihan Pelunasan kebuka.
--
-- Jalankan SETELAH tabel manufaktur_penawaran & turunannya sudah ada.

-- 1) Persetujuan Mirjov atas Form Penawaran Harga dari MMT.
--    Sengaja kolom sendiri (bukan cuma status='disetujui') supaya kecatat SIAPA & KAPAN —
--    ini yang jadi gerbang bolehnya Tagihan DP dibuat.
ALTER TABLE manufaktur_penawaran
  ADD COLUMN approved_by INT UNSIGNED NULL AFTER detail_updated_at,
  ADD COLUMN approved_at DATETIME NULL AFTER approved_by;

ALTER TABLE manufaktur_penawaran
  ADD CONSTRAINT fk_mp_approved_by FOREIGN KEY (approved_by) REFERENCES users(id);

-- 2) Berkas QC + foto barang jadi. Ditempel di level DOKUMEN (bukan per baris barang)
--    karena tagihannya juga per dokumen. Satu qc_type boleh punya banyak file.
--    qc_type dikunci di aplikasi (const MP_QC_TYPES), bukan ENUM, biar nambah jenis QC
--    baru nanti gak perlu ALTER TABLE di shared hosting.
CREATE TABLE manufaktur_penawaran_qc (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  manufaktur_penawaran_id INT UNSIGNED NOT NULL,
  qc_type VARCHAR(32) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  uploaded_by INT UNSIGNED NOT NULL,
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_mp_qc_doc (manufaktur_penawaran_id, qc_type),
  FOREIGN KEY (manufaktur_penawaran_id) REFERENCES manufaktur_penawaran(id) ON DELETE CASCADE,
  FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- 3) Tagihan DP & Pelunasan yang nempel ke dokumen penawaran.
--    Sengaja TERPISAH dari modul Invoicing umum: penomoran, alur, dan gerbang QC-nya
--    khusus manufaktur, jadi kalau digabung malah tabrakan sama Invoicing ERP biasa.
CREATE TABLE manufaktur_tagihan (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  manufaktur_penawaran_id INT UNSIGNED NOT NULL,
  doc_number VARCHAR(50) NOT NULL,
  tagihan_type ENUM('dp','pelunasan') NOT NULL,
  -- Nilai tagihan disnapshot saat dibuat: kalau harga di penawaran diubah belakangan,
  -- tagihan yang sudah terbit TIDAK ikut berubah.
  amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  percent DECIMAL(5,2) NULL,
  status ENUM('belum_dibayar','lunas','void') NOT NULL DEFAULT 'belum_dibayar',
  notes TEXT NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  paid_at DATETIME NULL,
  updated_by INT UNSIGNED NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_mp_tagihan_doc (organization_id, doc_number),
  KEY idx_mp_tagihan_penawaran (manufaktur_penawaran_id),
  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  FOREIGN KEY (manufaktur_penawaran_id) REFERENCES manufaktur_penawaran(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB;
