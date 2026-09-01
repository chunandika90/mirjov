<?php
require_once __DIR__ . '/../backoffice-shared/auth.php';
require_once __DIR__ . '/../backoffice-shared/modules.php';
require_once __DIR__ . '/../backoffice-shared/image_upload.php';
require_once __DIR__ . '/vendor-lite/fpdf/fpdf.php';
require_once __DIR__ . '/vendor-lite/fpdi/src/autoload.php';

use setasign\Fpdi\Fpdi;

/**
 * Tabel yang barisnya auto-wrap (tinggi baris ngikut konten terpanjang di
 * kolom manapun) — pola standar FPDF "NbLines" karena Cell() bawaan gak
 * bisa wrap teks, cuma MultiCell() yang bisa, tapi MultiCell gak native
 * dukung banyak kolom sejajar.
 */
class ManufakturPoPdf extends Fpdi
{
    public function NbLines($w, $txt)
    {
        $cw = $this->CurrentFont['cw'];
        if ($w == 0) $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', (string) $txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] == "\n") $nb--;
        $sep = -1; $i = 0; $j = 0; $l = 0; $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") { $i++; $sep = -1; $j = $i; $l = 0; $nl++; continue; }
            if ($c == ' ') $sep = $i;
            $l += $cw[$c] ?? 600;
            if ($l > $wmax) {
                if ($sep == -1) { if ($i == $j) $i++; } else { $i = $sep + 1; }
                $sep = -1; $j = $i; $l = 0; $nl++;
            } else {
                $i++;
            }
        }
        return $nl;
    }

    /** Gambar 1 baris tabel dengan lebar kolom tetap, tinggi ngikut konten terpanjang (word-wrap). */
    public function TableRow(array $widths, array $texts, $lineHeight = 5, $aligns = [], $fill = false)
    {
        $x = $this->GetX();
        $y = $this->GetY();
        $maxLines = 1;
        foreach ($widths as $i => $w) {
            $maxLines = max($maxLines, $this->NbLines($w, $texts[$i] ?? ''));
        }
        $rowH = $maxLines * $lineHeight;
        if ($this->GetY() + $rowH > $this->PageBreakTrigger) {
            $this->AddPage($this->CurOrientation);
            $x = $this->GetX();
            $y = $this->GetY();
        }
        $cx = $x;
        foreach ($widths as $i => $w) {
            if ($fill) { $this->Rect($cx, $y, $w, $rowH, 'F'); }
            $this->Rect($cx, $y, $w, $rowH);
            $cx += $w;
        }
        $cx = $x;
        foreach ($widths as $i => $w) {
            $this->SetXY($cx, $y);
            $this->MultiCell($w, $lineHeight, $texts[$i] ?? '', 0, $aligns[$i] ?? 'L');
            $cx += $w;
        }
        $this->SetXY($x, $y + $rowH);
        return $rowH;
    }

    /**
     * Grid kartu komponen — samain kayak .comp-grid di manufaktur-po-print.php:
     * kotak per komponen dengan judul (bar gelap), foto di tengah, lalu
     * Pembuat/Code/Material di bawahnya. Bukan tabel teks polos.
     */
    public function ComponentGrid(array $components, string $webrootDir, int $perRow = 4)
    {
        $marginL = $this->lMargin;
        $usableW = $this->w - $this->lMargin - $this->rMargin;
        $gap = 4;
        $boxW = ($usableW - ($perRow - 1) * $gap) / $perRow;
        $titleH = 6;
        $imgH = 30;
        $kvRowH = 5;
        $boxH = $titleH + $imgH + $kvRowH * 3;

        $chunks = array_chunk($components, $perRow);
        foreach ($chunks as $chunk) {
            if ($this->GetY() + $boxH > $this->PageBreakTrigger) {
                $this->AddPage($this->CurOrientation);
            }
            $startY = $this->GetY();
            foreach ($chunk as $i => $c) {
                $bx = $marginL + $i * ($boxW + $gap);
                $by = $startY;

                // Bar judul gelap, teks putih — sama kayak .comp-title
                $this->SetFillColor(28, 26, 23);
                $this->Rect($bx, $by, $boxW, $titleH, 'F');
                $this->SetTextColor(255, 255, 255);
                $this->SetFont('Arial', 'B', 7);
                $this->SetXY($bx, $by);
                $this->Cell($boxW, $titleH, mb_strtoupper($c['component_name'] ?? ''), 0, 0, 'C');
                $this->SetTextColor(0, 0, 0);

                // Area foto — sama kayak .comp-img
                $imgY = $by + $titleH;
                $this->Rect($bx, $imgY, $boxW, $imgH);
                $photoPath = $c['photo_path'] ?? null;
                if ($photoPath) {
                    $localImg = $webrootDir . '/' . $photoPath;
                    if (is_file($localImg)) {
                        $info = @getimagesize($localImg);
                        if ($info) {
                            [$iw, $ih] = $info;
                            $ratio = min(($boxW - 2) / $iw, ($imgH - 2) / $ih);
                            $dw = $iw * $ratio;
                            $dh = $ih * $ratio;
                            $this->Image($localImg, $bx + ($boxW - $dw) / 2, $imgY + ($imgH - $dh) / 2, $dw, $dh);
                        }
                    }
                }

                // Baris Pembuat/Code/Material — sama kayak .comp-kv
                $kv = [['Pembuat', $c['pembuat'] ?? ''], ['Code', $c['code'] ?? ''], ['Material', $c['material'] ?? '']];
                $kvY = $imgY + $imgH;
                foreach ($kv as $ri => $row) {
                    $ry = $kvY + $ri * $kvRowH;
                    $this->Rect($bx, $ry, $boxW, $kvRowH);
                    $this->SetFont('Arial', 'B', 6.5);
                    $this->SetXY($bx + 1, $ry + 1);
                    $this->Cell($boxW * 0.4, $kvRowH - 2, $row[0], 0, 0, 'L');
                    $this->SetFont('Arial', '', 6.5);
                    $this->SetXY($bx + 1 + $boxW * 0.4, $ry + 1);
                    $this->Cell($boxW * 0.6 - 2, $kvRowH - 2, (string) $row[1], 0, 0, 'L');
                }
            }
            $this->SetXY($marginL, $startY + $boxH + 6);
        }
    }
}

require_login();
$org = require_org();
require_module_access('manufaktur_po');

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    'SELECT h.*, c.name AS vendor_name, c.address, p.name AS project_name
     FROM manufaktur_po h JOIN contacts c ON c.id=h.vendor_id LEFT JOIN projects p ON p.id=h.project_id
     WHERE h.id=? AND h.organization_id=?'
);
$stmt->execute([$id, $org['organization_id']]);
$h = $stmt->fetch();
if (!$h) { http_response_code(404); exit('Form Product Series tidak ditemukan.'); }

$lines = $pdo->prepare('SELECT * FROM manufaktur_po_lines WHERE manufaktur_po_id=?');
$lines->execute([$id]);
$lines = $lines->fetchAll();

$compStmt = $pdo->prepare('SELECT * FROM manufaktur_po_line_components WHERE line_id=?');
$attStmt = $pdo->prepare("SELECT file_path, original_name FROM manufaktur_po_line_attachments WHERE line_id=? AND source='mj' ORDER BY id");
$allImages = [];
$allPdfFiles = [];
foreach ($lines as &$l) {
    $compStmt->execute([$l['id']]);
    $l['components'] = $compStmt->fetchAll();

    $attStmt->execute([$l['id']]);
    foreach ($attStmt->fetchAll() as $a) {
        $ext = strtolower(pathinfo($a['file_path'], PATHINFO_EXTENSION));
        $localPath = webroot_dir() . '/' . $a['file_path'];
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) && is_file($localPath)) {
            $allImages[] = ['path' => $localPath, 'label' => $l['product_name_snapshot']];
        } elseif ($ext === 'pdf' && is_file($localPath)) {
            $allPdfFiles[] = ['path' => $localPath, 'name' => $a['original_name']];
        }
    }
}
unset($l);

function tgl_indo_po_pdf(?string $dateStr): string
{
    if (!$dateStr) return '-';
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $ts = strtotime($dateStr);
    return (int) date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

/**
 * Paksa wrap manual (sisip \n) sebelum masuk TableRow — jaga-jaga biar teks bebas panjang
 * (Remarks dll) gak pernah ngejorok keluar kotak, gak cuma ngandelin estimasi lebar karakter FPDF.
 */
function pdf_wrap_text_po(string $text, int $charsPerLine): string
{
    return wordwrap($text, $charsPerLine, "\n", true);
}

$pdf = new ManufakturPoPdf('P', 'mm', 'A4');
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

// ===== Header =====
$logoPath = webroot_dir() . '/assets/img/logo-mirjov.png';
if (is_file($logoPath)) $pdf->Image($logoPath, 15, 13, 14, 14);
$pdf->SetXY(32, 17);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(90, 6, 'MIRJOV KARUNIA ABADI', 0, 0, 'L');

$pdf->SetFont('Arial', 'B', 13);
$pdf->SetXY(15, 30);
$pdf->Cell(0, 6, 'PURCHASE ORDER', 0, 1, 'C');

$pdf->SetFont('Arial', '', 9);
$pdf->SetXY(140, 13);
$pdf->Cell(20, 5, 'Tanggal', 0, 0, 'L'); $pdf->Cell(3, 5, ':', 0, 0, 'L'); $pdf->Cell(35, 5, tgl_indo_po_pdf($h['tanggal']), 0, 1, 'L');
$pdf->SetXY(140, 18);
$pdf->Cell(20, 5, 'Nomor', 0, 0, 'L'); $pdf->Cell(3, 5, ':', 0, 0, 'L'); $pdf->Cell(35, 5, $h['doc_number'], 0, 1, 'L');
if ($h['po_number_vendor']) {
    $pdf->SetXY(140, 23);
    $pdf->Cell(20, 5, 'No. PO Vendor', 0, 0, 'L'); $pdf->Cell(3, 5, ':', 0, 0, 'L'); $pdf->Cell(35, 5, $h['po_number_vendor'], 0, 1, 'L');
}

$pdf->SetY(40);
$pdf->SetLineWidth(0.3);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->SetLineWidth(0.2);
$pdf->Ln(4);

// ===== Info Vendor/Project — kolom kiri x=15..110, kolom kanan x=110..195 =====
$infoY = $pdf->GetY();
$pdf->SetFont('Arial', '', 9);
$pdf->SetXY(15, $infoY);
$pdf->Cell(20, 6, 'Vendor', 0, 0); $pdf->Cell(3, 6, ':', 0, 0); $pdf->Cell(67, 6, $h['vendor_name']);
$pdf->SetXY(110, $infoY);
$pdf->Cell(20, 6, 'Project', 0, 0); $pdf->Cell(3, 6, ':', 0, 0); $pdf->Cell(62, 6, (string) $h['project_name']);
$pdf->Ln(6);
$pdf->SetX(15);
$pdf->Cell(20, 6, 'Pemesan', 0, 0); $pdf->Cell(3, 6, ':', 0, 0); $pdf->Cell(67, 6, (string) $h['pemesan']);
$pdf->SetXY(110, $pdf->GetY());
$pdf->Cell(20, 6, 'Wkt. Produksi', 0, 0); $pdf->Cell(3, 6, ':', 0, 0); $pdf->Cell(62, 6, (string) $h['waktu_produksi']);
$pdf->Ln(6);
if ($h['keterangan']) {
    $pdf->SetX(15);
    $pdf->Cell(20, 6, 'Keterangan', 0, 0); $pdf->Cell(3, 6, ':', 0, 0); $pdf->Cell(0, 6, $h['keterangan']);
    $pdf->Ln(6);
}
$pdf->Ln(2);

// ===== Tabel barang per line (auto-wrap, tinggi baris ngikut konten terpanjang) =====
$colW = [45, 30, 25, 25, 25, 45];
$aligns = ['L', 'L', 'C', 'C', 'C', 'L'];
foreach ($lines as $l) {
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(240, 237, 230);
    $pdf->TableRow($colW, ['Produk', 'Series', 'Size (mm)', 'Qty', 'Item Code', 'Remarks'], 5, array_fill(0, 6, 'C'), true);

    $pdf->SetFont('Arial', '', 8);
    $pdf->TableRow($colW, [
        pdf_wrap_text_po($l['product_name_snapshot'], 22),
        pdf_wrap_text_po($l['series'] ?? '-', 14),
        pdf_wrap_text_po($l['size_mm'] ?? '-', 12),
        rtrim(rtrim(number_format((float) $l['qty'], 2, ',', '.'), '0'), ','),
        pdf_wrap_text_po($l['item_code'] ?? '-', 12),
        pdf_wrap_text_po($l['remarks'] ?? '-', 22),
    ], 5, $aligns);
    $pdf->Ln(3);

    if ($l['components']) {
        $pdf->ComponentGrid($l['components'], webroot_dir());
    }
    $pdf->Ln(6);
}

// ===== Catatan =====
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 5, 'NOTE:', 0, 1);
$pdf->Cell(0, 5, '1. Pembayaran: 50% DP dan 50% setelah barang selesai.', 0, 1);
$pdf->Ln(10);

// ===== Tanda tangan =====
$sigY = $pdf->GetY();
$pdf->SetFont('Arial', '', 9);
$pdf->SetXY(15, $sigY);
$pdf->Cell(85, 5, 'Pemesan', 0, 0, 'C');
$pdf->SetXY(110, $sigY);
$pdf->Cell(85, 5, 'Pembuat (Vendor)', 0, 0, 'C');

$pdf->Line(15, $sigY + 25, 100, $sigY + 25);
$pdf->Line(110, $sigY + 25, 195, $sigY + 25);
$pdf->SetXY(110, $sigY + 26);
$pdf->Cell(85, 5, '( ' . $h['vendor_name'] . ' )', 0, 0, 'C');

// ===== Halaman lampiran gambar kerja (2 per halaman, ukuran asli/proporsional) =====
$photoChunks = array_chunk($allImages, 2);
foreach ($photoChunks as $chunk) {
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Lampiran Gambar Kerja - ' . $h['doc_number'], 'B', 1);
    $pdf->Ln(4);
    $slotH = 125;
    foreach ($chunk as $img) {
        $y = $pdf->GetY();
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 5, $img['label'], 0, 1, 'C');
        [$w, $h2] = @getimagesize($img['path']) ?: [800, 600];
        $ratio = min(170 / $w, ($slotH - 10) / $h2);
        $drawW = $w * $ratio; $drawH = $h2 * $ratio;
        $pdf->Image($img['path'], (210 - $drawW) / 2, $pdf->GetY() + 2, $drawW, $drawH);
        $pdf->SetY($y + $slotH);
    }
}

// ===== Sisipkan halaman PDF yang di-attach (gabung beneran) =====
foreach ($allPdfFiles as $pdfFile) {
    try {
        $pageCount = $pdf->setSourceFile($pdfFile['path']);
        for ($p = 1; $p <= $pageCount; $p++) {
            $tplId = $pdf->importPage($p);
            $size = $pdf->getTemplateSize($tplId);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($tplId);
        }
    } catch (Throwable $e) {
        // PDF terenkripsi/rusak — skip, jangan gagalin seluruh dokumen.
        continue;
    }
}

$pdf->Output('I', 'Purchase-Order-' . str_replace('/', '-', $h['doc_number']) . '.pdf');
