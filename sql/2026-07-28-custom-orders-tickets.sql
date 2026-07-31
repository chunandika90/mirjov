-- Ubah custom_orders jadi model "tiket": status pending/processed/void +
-- field balasan admin (admin_reply) biar tiap tiket bisa dijawab dari CMS.

-- Mapping status lama -> baru dulu (biar data existing gak error pas ALTER enum):
--   in_progress -> pending (masih dikerjain, belum kelar)
--   completed   -> processed
UPDATE custom_orders SET status = 'in_progress_tmp' WHERE status = 'in_progress';
UPDATE custom_orders SET status = 'completed_tmp' WHERE status = 'completed';

ALTER TABLE custom_orders MODIFY COLUMN status ENUM('pending','processed','void','in_progress_tmp','completed_tmp') NOT NULL DEFAULT 'pending';

UPDATE custom_orders SET status = 'pending' WHERE status = 'in_progress_tmp';
UPDATE custom_orders SET status = 'processed' WHERE status = 'completed_tmp';

ALTER TABLE custom_orders MODIFY COLUMN status ENUM('pending','processed','void') NOT NULL DEFAULT 'pending';

ALTER TABLE custom_orders ADD COLUMN admin_reply TEXT NULL AFTER request;
ALTER TABLE custom_orders ADD COLUMN replied_at DATETIME NULL AFTER admin_reply;
