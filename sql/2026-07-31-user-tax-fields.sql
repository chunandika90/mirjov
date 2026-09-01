-- Data pajak per user (associate/sales) buat hitung komisi associate: badan/perorangan
-- nentuin rate PPh 23 (badan 2%, perorangan 2.5%), subject_to_pph buat associate yang
-- gak kena potongan pajak (komisi full, gak dipotong).
ALTER TABLE users
  ADD COLUMN entity_type ENUM('perorangan','badan') NOT NULL DEFAULT 'perorangan' AFTER status,
  ADD COLUMN subject_to_pph TINYINT(1) NOT NULL DEFAULT 1 AFTER entity_type;
