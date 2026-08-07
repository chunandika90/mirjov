<?php
/**
 * Cegah halaman dinamis (data dari CMS) ke-cache oleh browser ATAU cache
 * server-side kayak LiteSpeed Cache (LSCache) — banyak shared hosting
 * otomatis nge-cache respons PHP kecuali diberitahu eksplisit jangan.
 * Tanpa ini, edit di CMS bisa gak langsung kelihatan di situs publik.
 */
function no_cache_headers(): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-LiteSpeed-Cache-Control: no-cache');
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-') ?: bin2hex(random_bytes(4));
}

/**
 * Baris kecil "Dibuat [nama] · [tanggal]  ·  Diubah [nama] · [tanggal]" —
 * dipasang di pojok kanan tiap kartu/baris konten CMS, bukan kolom sendiri.
 * $updatedAt di-skip kalau sama persis sama $createdAt (belum pernah diedit).
 */
function render_audit_trail(?string $createdByName, ?string $createdAt, ?string $updatedByName = null, ?string $updatedAt = null): string
{
    $parts = [];
    if ($createdAt) {
        $who = $createdByName !== null && $createdByName !== '' ? htmlspecialchars($createdByName) : '—';
        $parts[] = 'Dibuat <b>' . $who . '</b> · ' . date('d M Y', strtotime($createdAt));
    }
    if ($updatedAt && $updatedAt !== $createdAt) {
        $who = $updatedByName !== null && $updatedByName !== '' ? htmlspecialchars($updatedByName) : '—';
        $parts[] = 'Diubah <b>' . $who . '</b> · ' . date('d M Y', strtotime($updatedAt));
    }
    if (!$parts) return '';
    return '<div class="audit-trail">' . implode(' &nbsp;&middot;&nbsp; ', $parts) . '</div>';
}

/**
 * Terima link YouTube format apa aja (watch?v=, youtu.be/, embed/, shorts/) ATAU
 * ID mentahnya langsung — selalu balikin cuma ID-nya doang buat disimpan ke DB.
 * Kalau formatnya gak dikenali, dibalikin apa adanya (di-trim) — biar admin CMS
 * gak perlu mikirin format, tinggal paste link lengkap dari address bar.
 */
function extract_youtube_id(string $input): string
{
    $input = trim($input);
    if ($input === '') return '';

    $patterns = [
        '#youtu\.be/([A-Za-z0-9_-]{6,})#',
        '#youtube\.com/watch\?v=([A-Za-z0-9_-]{6,})#',
        '#youtube\.com/embed/([A-Za-z0-9_-]{6,})#',
        '#youtube\.com/shorts/([A-Za-z0-9_-]{6,})#',
        '#youtube\.com/live/([A-Za-z0-9_-]{6,})#',
    ];
    foreach ($patterns as $p) {
        if (preg_match($p, $input, $m)) return $m[1];
    }

    return $input;
}

function unique_slug(PDO $pdo, string $table, string $base, int $excludeId = 0): string
{
    $slug = slugify($base);
    $original = $slug;
    $i = 2;
    while (true) {
        $stmt = $pdo->prepare("SELECT COUNT(*) c FROM {$table} WHERE slug = ? AND id != ?");
        $stmt->execute([$slug, $excludeId]);
        if ((int) $stmt->fetch()['c'] === 0) {
            return $slug;
        }
        $slug = $original . '-' . $i;
        $i++;
    }
}
