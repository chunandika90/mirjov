<?php
/**
 * SCRIPT MIGRASI SEKALI PAKAI — Manufaktur: Approval, Form QC, Tagihan DP/Pelunasan.
 * Isinya sama persis dengan sql/2026-09-04-manufaktur-approval-qc-tagihan.sql,
 * cuma dibungkus PHP biar bisa dijalanin lewat browser (shared hosting gak kasih
 * akses SQL dari luar).
 *
 * PENTING:
 *  - Cuma bisa dibuka role Owner, dan cuma jalan lewat POST + CSRF.
 *  - Idempoten: tiap langkah ngecek dulu ke information_schema, yang sudah ada dilewati.
 *    Jadi aman kalau kepencet dua kali.
 *  - HAPUS FILE INI setelah migrasi sukses (lihat commit f761c66 buat pola yang sama).
 */
$pageTitle = 'Migrasi DB — Manufaktur QC & Tagihan';
$activeMenu = 'manufaktur_penawaran';
require __DIR__ . '/includes/header.php';

// Gerbang: Owner doang. Migrasi itu operasi struktural, bukan operasi harian.
if (($org['role_name'] ?? '') !== 'Owner') {
    http_response_code(403);
    exit('Migrasi database cuma boleh dijalanin role Owner.');
}

$pdo = db();

/** Apakah kolom $column sudah ada di $table pada database yang lagi kepakai? */
function mig_has_column(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS
                           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function mig_has_table(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES
                           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function mig_has_constraint(PDO $pdo, string $name): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                           WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = ?');
    $stmt->execute([$name]);
    return (int) $stmt->fetchColumn() > 0;
}

// Tiap langkah: label, cek "sudah ada?", dan SQL-nya.
$steps = [
    [
        'label' => 'Kolom manufaktur_penawaran.approved_by',
        'done' => fn() => mig_has_column($pdo, 'manufaktur_penawaran', 'approved_by'),
        'sql' => 'ALTER TABLE manufaktur_penawaran ADD COLUMN approved_by INT UNSIGNED NULL AFTER detail_updated_at',
    ],
    [
        'label' => 'Kolom manufaktur_penawaran.approved_at',
        'done' => fn() => mig_has_column($pdo, 'manufaktur_penawaran', 'approved_at'),
        'sql' => 'ALTER TABLE manufaktur_penawaran ADD COLUMN approved_at DATETIME NULL AFTER approved_by',
    ],
    [
        'label' => 'Foreign key fk_mp_approved_by',
        'done' => fn() => mig_has_constraint($pdo, 'fk_mp_approved_by'),
        'sql' => 'ALTER TABLE manufaktur_penawaran
                  ADD CONSTRAINT fk_mp_approved_by FOREIGN KEY (approved_by) REFERENCES users(id)',
    ],
    [
        'label' => 'Tabel manufaktur_penawaran_qc',
        'done' => fn() => mig_has_table($pdo, 'manufaktur_penawaran_qc'),
        'sql' => 'CREATE TABLE manufaktur_penawaran_qc (
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
                  ) ENGINE=InnoDB',
    ],
    [
        'label' => 'Tabel manufaktur_tagihan',
        'done' => fn() => mig_has_table($pdo, 'manufaktur_tagihan'),
        'sql' => "CREATE TABLE manufaktur_tagihan (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    organization_id INT UNSIGNED NOT NULL,
                    manufaktur_penawaran_id INT UNSIGNED NOT NULL,
                    doc_number VARCHAR(50) NOT NULL,
                    tagihan_type ENUM('dp','pelunasan') NOT NULL,
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
                  ) ENGINE=InnoDB",
    ],
];

$results = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $results = [];
    foreach ($steps as $step) {
        if (($step['done'])()) {
            $results[] = ['skip', $step['label'], 'Sudah ada — dilewati.'];
            continue;
        }
        try {
            // DDL bikin implicit commit di MySQL, jadi sengaja gak dibungkus transaksi.
            $pdo->exec($step['sql']);
            $results[] = ['ok', $step['label'], 'Dibuat.'];
        } catch (Throwable $e) {
            $results[] = ['error', $step['label'], $e->getMessage()];
            break; // langkah berikutnya bisa bergantung ke yang gagal — stop di sini.
        }
    }
}

$allDone = true;
foreach ($steps as $step) {
    if (!($step['done'])()) { $allDone = false; break; }
}
?>

<style>
  .mig-row { display:flex; gap:10px; align-items:baseline; padding:9px 0; border-bottom:1px solid var(--border); font-size:13px; }
  .mig-row:last-child { border-bottom:none; }
  .mig-chip { font-size:10.5px; font-weight:700; padding:2px 8px; border-radius:999px; white-space:nowrap; }
  .mig-chip.ok { background:var(--ok-bg, #e7f6ec); color:var(--ok, #2f9e5e); }
  .mig-chip.pending { background:var(--amber-bg, #fdf3e2); color:var(--amber, #a3741f); }
  .mig-chip.error { background:var(--danger-bg, #fde2e2); color:var(--danger, #b91c1c); }
  .mig-note { font-size:12px; color:var(--ink-muted); }
</style>

<div class="card" style="max-width:760px;">
  <h2 style="margin:0 0 4px; font-size:18px;">Migrasi DB — Approval, Form QC &amp; Tagihan</h2>
  <p class="mig-note" style="margin:0 0 16px;">
    Nambah 2 kolom dan 2 tabel buat alur baru Form Penawaran Harga.
    Cuma nambah — gak ada data lama yang diubah atau dihapus. Aman diulang.
  </p>

  <?php if ($results !== null): ?>
    <h3 style="font-size:13px; margin:0 0 8px;">Hasil</h3>
    <?php foreach ($results as [$kind, $label, $msg]): ?>
      <div class="mig-row">
        <span class="mig-chip <?= $kind === 'ok' ? 'ok' : ($kind === 'skip' ? 'pending' : 'error') ?>">
          <?= $kind === 'ok' ? 'DIBUAT' : ($kind === 'skip' ? 'DILEWATI' : 'GAGAL') ?>
        </span>
        <span><strong><?= htmlspecialchars($label) ?></strong> — <?= htmlspecialchars($msg) ?></span>
      </div>
    <?php endforeach; ?>
    <hr style="margin:16px 0; border:none; border-top:1px solid var(--border);">
  <?php endif; ?>

  <h3 style="font-size:13px; margin:0 0 8px;">Kondisi sekarang</h3>
  <?php foreach ($steps as $step): $exists = ($step['done'])(); ?>
    <div class="mig-row">
      <span class="mig-chip <?= $exists ? 'ok' : 'pending' ?>"><?= $exists ? '✔ ADA' : '○ BELUM' ?></span>
      <span><?= htmlspecialchars($step['label']) ?></span>
    </div>
  <?php endforeach; ?>

  <div style="margin-top:18px;">
    <?php if ($allDone): ?>
      <div style="padding:12px 14px; border-radius:10px; background:var(--ok-bg, #e7f6ec); border:1px solid var(--ok, #2f9e5e); font-size:12.5px;">
        ✔ Migrasi lengkap. Modul Manufaktur sudah siap — <strong>file ini boleh dihapus.</strong>
      </div>
      <div style="margin-top:12px;"><a class="btn" href="manufaktur-penawaran.php">Buka Form Penawaran Harga</a></div>
    <?php else: ?>
      <form method="post" onsubmit="return confirm('Jalankan migrasi database sekarang?');">
        <?= csrf_field() ?>
        <button type="submit" class="btn">Jalankan Migrasi</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
