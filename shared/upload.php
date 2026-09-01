<?php
/**
 * Upload gambar yang aman: validasi MIME asli (bukan cuma ekstensi),
 * validasi ukuran, nama file di-random supaya tidak bisa ditebak/dioverwrite,
 * dan disimpan di luar folder yang bisa mengeksekusi PHP.
 *
 * Tiap gambar yang diupload otomatis di-resize jadi 2 versi (butuh ekstensi
 * GD PHP, ada di hampir semua shared hosting):
 *   - versi utama, dibatasi maksimal THUMB_DETAIL_MAX_DIM px di sisi
 *     terpanjang — dipakai di halaman detail (product.php dkk)
 *   - versi "-thumb", dibatasi maksimal THUMB_GRID_MAX_DIM px — dipakai di
 *     grid/listing (category.php, daftar di admin panel) via image_thumb_url()
 * Foto yang lebih kecil dari batas itu TIDAK di-upscale, dibiarkan aslinya.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

const ALLOWED_IMAGE_MIME = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];
const MAX_IMAGE_BYTES = 5 * 1024 * 1024; // 5MB
const THUMB_DETAIL_MAX_DIM = 1600;
const THUMB_GRID_MAX_DIM = 800;

/**
 * @param array $file salah satu elemen $_FILES, mis. $_FILES['image']
 * @param string $subfolder mis. 'homepage/hero', 'products/cover'
 * @param string $nameHint nama produk/proyek/blog/dll — kalau diisi, nama file
 *   jadi "nama-produk-a3f9c1.jpg" (gampang dikenali di File Manager, SEO-friendly),
 *   bukan cuma hex acak. Kalau dikosongin, fallback ke hex acak penuh.
 * @return string path relatif tersimpan (buat disimpan di kolom image_path DB)
 * @throws RuntimeException kalau validasi gagal
 */
function handle_image_upload(array $file, string $subfolder, string $nameHint = ''): string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Tidak ada file dipilih.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload gagal (kode error: ' . $file['error'] . ').');
    }
    if ($file['size'] > MAX_IMAGE_BYTES) {
        throw new RuntimeException('Ukuran file maksimal 5MB.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset(ALLOWED_IMAGE_MIME[$mime])) {
        throw new RuntimeException('Format file harus JPG, PNG, atau WEBP.');
    }
    $ext = ALLOWED_IMAGE_MIME[$mime];

    $targetDir = rtrim(UPLOAD_DIR_BASE, '/') . '/' . trim($subfolder, '/');
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Gagal menyiapkan folder upload.');
    }

    $uniquePart = bin2hex(random_bytes(3)); // 6 karakter — cukup buat hindari bentrok nama
    $filename = ($nameHint !== '' ? slugify($nameHint) . '-' . $uniquePart : bin2hex(random_bytes(16))) . '.' . $ext;
    $destination = $targetDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Gagal menyimpan file yang diupload.');
    }

    // Resize versi utama (detail) in-place, lalu bikin versi thumb terpisah.
    // Kalau GD gak ada di server, dilewatin aja — file asli tetap kepakai apa adanya.
    resize_image_file($destination, $mime, THUMB_DETAIL_MAX_DIM);
    $thumbDestination = thumb_variant_path($destination);
    if (@copy($destination, $thumbDestination)) {
        resize_image_file($thumbDestination, $mime, THUMB_GRID_MAX_DIM);
    }

    return trim($subfolder, '/') . '/' . $filename;
}

/**
 * Resize file gambar in-place, dibatasi $maxDim px di sisi terpanjang,
 * aspect ratio dijaga. Tidak upscale kalau gambarnya udah lebih kecil.
 */
function resize_image_file(string $filePath, string $mime, int $maxDim): void
{
    if (!extension_loaded('gd')) return;

    $info = @getimagesize($filePath);
    if (!$info) return;
    [$width, $height] = $info;
    if ($width <= $maxDim && $height <= $maxDim) return;

    if ($mime === 'image/jpeg') {
        $src = @imagecreatefromjpeg($filePath);
    } elseif ($mime === 'image/png') {
        $src = @imagecreatefrompng($filePath);
    } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
        $src = @imagecreatefromwebp($filePath);
    } else {
        return;
    }
    if (!$src) return;

    $ratio = min($maxDim / $width, $maxDim / $height);
    $newWidth = max(1, (int) round($width * $ratio));
    $newHeight = max(1, (int) round($height * $ratio));

    $dst = imagecreatetruecolor($newWidth, $newHeight);
    if ($mime === 'image/png') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    if ($mime === 'image/jpeg') {
        imagejpeg($dst, $filePath, 85);
    } elseif ($mime === 'image/png') {
        imagepng($dst, $filePath, 6);
    } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
        imagewebp($dst, $filePath, 85);
    }

    imagedestroy($src);
    imagedestroy($dst);
}

function thumb_variant_path(string $path): string
{
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    $base = substr($path, 0, -(strlen($ext) + 1));
    return $base . '-thumb.' . $ext;
}

function image_url(?string $relativePath): string
{
    if (!$relativePath) {
        return '';
    }
    // URL absolut (mis. hasil migrasi konten lama, sudah termasuk domain) dibiarkan apa
    // adanya; path relatif dari CMS baru (mis. "products/cover/x.jpg") diprefix UPLOAD_URL_BASE.
    // PENTING: jangan pernah simpan path "/assets/..." tanpa domain — admin panel & situs
    // ada di subdomain BEDA, path root-relative bakal salah nunjuk domain kalau dibuka dari admin.
    if (str_starts_with($relativePath, 'http://') || str_starts_with($relativePath, 'https://')) {
        return $relativePath;
    }
    return rtrim(UPLOAD_URL_BASE, '/') . '/' . ltrim($relativePath, '/');
}

/**
 * Versi thumbnail (lebih kecil, buat grid/listing) dari sebuah gambar.
 * Gambar hasil migrasi konten lama (URL absolut) belum punya versi thumb
 * terpisah — fallback ke gambar aslinya.
 */
function image_thumb_url(?string $relativePath): string
{
    if (!$relativePath) {
        return '';
    }
    if (str_starts_with($relativePath, 'http://') || str_starts_with($relativePath, 'https://')) {
        return $relativePath;
    }
    $ext = pathinfo($relativePath, PATHINFO_EXTENSION);
    $base = substr($relativePath, 0, -(strlen($ext) + 1));
    return rtrim(UPLOAD_URL_BASE, '/') . '/' . $base . '-thumb.' . $ext;
}

function delete_uploaded_image(?string $relativePath): void
{
    if (!$relativePath) {
        return;
    }
    $full = rtrim(UPLOAD_DIR_BASE, '/') . '/' . ltrim($relativePath, '/');
    if (is_file($full)) {
        @unlink($full);
    }
    $thumb = thumb_variant_path($full);
    if (is_file($thumb)) {
        @unlink($thumb);
    }
}
