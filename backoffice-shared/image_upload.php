<?php
/**
 * Upload + resize foto produk. File asli TIDAK disimpan — langsung di-resize
 * ke max dimensi tertentu terus disave sebagai JPEG, biar hemat storage &
 * konsisten dipakai di layar cetak.
 */

function save_resized_product_photo(array $file, int $maxDim = 1000): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload foto gagal.');
    }
    $info = @getimagesize($file['tmp_name']);
    if (!$info) throw new RuntimeException('File bukan gambar yang valid.');
    [$width, $height, $type] = $info;

    $src = match ($type) {
        IMAGETYPE_JPEG => imagecreatefromjpeg($file['tmp_name']),
        IMAGETYPE_PNG => imagecreatefrompng($file['tmp_name']),
        IMAGETYPE_WEBP => imagecreatefromwebp($file['tmp_name']),
        default => throw new RuntimeException('Format gambar harus JPEG, PNG, atau WEBP.'),
    };

    $scale = min(1, $maxDim / max($width, $height));
    $newW = max(1, (int) round($width * $scale));
    $newH = max(1, (int) round($height * $scale));

    $dst = imagecreatetruecolor($newW, $newH);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);

    $dir = webroot_dir() . '/uploads/products';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $filename = bin2hex(random_bytes(8)) . '.jpg';
    imagejpeg($dst, $dir . '/' . $filename, 82);

    return 'uploads/products/' . $filename;
}

function delete_product_photo(?string $relativePath): void
{
    if (!$relativePath) return;
    $full = webroot_dir() . '/' . $relativePath;
    if (is_file($full)) @unlink($full);
}

/**
 * Folder webroot subdomain ini — TIDAK di-hardcode nama foldernya (beda antara dev lokal
 * "backoffice/" dan production "backoffice.svashtahome.com/"), pakai DOCUMENT_ROOT yang
 * server web set sendiri biar portable di dua-duanya.
 */
function webroot_dir(): string
{
    return $_SERVER['DOCUMENT_ROOT'] ?: (__DIR__ . '/../backoffice');
}

/**
 * Upload "gambar kerja" (working drawing) untuk detail line Manufaktur —
 * terima gambar ATAU PDF apa adanya (tanpa resize, drawing harus tetap utuh),
 * cuma whitelist ekstensi + random filename.
 */
const MANUFAKTUR_ATTACHMENT_ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

function save_manufaktur_line_attachment(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload gambar kerja gagal.');
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, MANUFAKTUR_ATTACHMENT_ALLOWED_EXT, true)) {
        throw new RuntimeException('Format gambar kerja harus JPG, PNG, WEBP, atau PDF.');
    }
    $dir = webroot_dir() . '/uploads/manufaktur';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $filename = bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
        throw new RuntimeException('Gagal menyimpan gambar kerja.');
    }
    return ['file_path' => 'uploads/manufaktur/' . $filename, 'original_name' => $file['name']];
}

function delete_manufaktur_line_attachment(?string $relativePath): void
{
    if (!$relativePath) return;
    $full = webroot_dir() . '/' . $relativePath;
    if (is_file($full)) @unlink($full);
}

/**
 * PHP gak auto-normalize nested file input array — untuk field
 * name="lines[<i>][attachments][]", $_FILES['lines'] datang dalam bentuk
 * ['name'=>[i=>['attachments'=>[j=>...]]], 'type'=>[...], ...] (transposed
 * cuma di level paling luar). Reshape jadi list file tunggal per line index.
 */
function normalize_manufaktur_line_files(array $filesLines, int $lineIndex): array
{
    $out = [];
    $names = $filesLines['name'][$lineIndex]['attachments'] ?? [];
    foreach ($names as $j => $name) {
        $error = $filesLines['error'][$lineIndex]['attachments'][$j] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) continue;
        $out[] = [
            'name' => $name,
            'type' => $filesLines['type'][$lineIndex]['attachments'][$j],
            'tmp_name' => $filesLines['tmp_name'][$lineIndex]['attachments'][$j],
            'error' => $error,
            'size' => $filesLines['size'][$lineIndex]['attachments'][$j],
        ];
    }
    return $out;
}
