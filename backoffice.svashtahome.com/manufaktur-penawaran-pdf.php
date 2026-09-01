<?php
require_once __DIR__ . '/../backoffice-shared/auth.php';
require_once __DIR__ . '/../backoffice-shared/modules.php';
require_once __DIR__ . '/../backoffice-shared/image_upload.php';
require_once __DIR__ . '/vendor-lite/fpdf/fpdf.php';
require_once __DIR__ . '/vendor-lite/fpdi/src/autoload.php';

use setasign\Fpdi\Fpdi;

const MPPDF_PRICE_CATEGORY_SPEC = [
    'harga_frame' => ['label' => 'Biaya Frame / Konstruksi', 'detail' => 'Material utama & pembuatan struktur'],
    'harga_qc' => ['label' => 'Biaya QC', 'detail' => 'QC barang produksi'],
    'harga_finishing' => ['label' => 'Biaya Finishing', 'detail' => 'Pengecatan / laminasi / polishing'],
    'harga_komponen' => ['label' => 'Biaya Komponen', 'detail' => 'Hardware / adjuster / rel / dll'],
    'harga_packaging' => ['label' => 'Biaya Packaging / Pengemasan', 'detail' => 'Bubble wrap, wooden crate, karton'],
    'harga_dll' => ['label' => 'Biaya Tambahan / Lain-lain', 'detail' => 'Pengiriman / Instalasi / aksesoris'],
];

/**
 * Cetakan "FORM PURCHASE ORDER + PENAWARAN HARGA" — 1 halaman per baris barang,
 * layoutnya sengaja disamain persis kayak contoh form MJ-MMT (banner navy, bar
 * abu-abu per section, tabel spesifikasi 2 kolom, tabel harga + grand total oranye).
 */
class ManufakturPenawaranPdf extends Fpdi
{
    public function Banner(string $title, string $subtitle)
    {
        $y = $this->GetY();
        $logoPath = webroot_dir() . '/assets/img/logo-mirjov.png';
        if (is_file($logoPath)) $this->Image($logoPath, 15, $y, 12, 12);
        $this->SetFont('Arial', 'B', 8.5);
        $this->SetXY(29, $y + 3);
        $this->Cell(60, 5, 'MIRJOV KARUNIA ABADI', 0, 0, 'L');

        $this->SetFont('Arial', 'B', 13);
        $this->SetXY(15, $y);
        $this->Cell(180, 6, mb_strtoupper($title), 0, 1, 'C');
        $this->SetFont('Arial', 'I', 8.5);
        $this->SetX(15);
        $this->Cell(180, 5, $subtitle, 0, 1, 'C');
        $this->SetY(max($this->GetY(), $y + 14));
        $this->SetLineWidth(0.3);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->SetLineWidth(0.2);
        $this->Ln(2);
    }

    public function SectionBar(string $left, string $right = '')
    {
        $y = $this->GetY();
        $this->Rect(15, $y, 180, 6.5);
        $this->SetLineWidth(0.15);
        $this->Line(15, $y, 195, $y);
        $this->Line(15, $y + 6.5, 195, $y + 6.5);
        $this->SetFont('Arial', 'B', 9);
        $this->SetXY(16, $y + 1);
        $this->Cell(120, 4.5, $left, 0, 0, 'L');
        if ($right !== '') {
            $this->SetXY(16, $y + 1);
            $this->Cell(179, 4.5, $right, 0, 0, 'R');
        }
        $this->SetY($y + 6.5);
    }

    /**
     * Baris tabel spesifikasi 2 kolom label+value (kolom kiri & kanan sejajar) — value-nya
     * di-wrap manual (wordwrap + MultiCell) biar teks panjang (Remark dll) gak pernah
     * ngejorok keluar kotak, tinggi baris nyesuain jumlah baris hasil wrap.
     */
    public function SpecRow($l1, $v1, $l2 = null, $v2 = null, $rowH = 6)
    {
        $x = 15; $y = $this->GetY();
        $w = [42, 48, 42, 48];
        $lineH = 4.5;

        $v1Wrapped = wordwrap((string) $v1, 26, "\n", true);
        $v1Lines = substr_count($v1Wrapped, "\n") + 1;
        $v2Wrapped = $l2 !== null ? wordwrap((string) $v2, 26, "\n", true) : '';
        $v2Lines = $l2 !== null ? substr_count($v2Wrapped, "\n") + 1 : 1;
        $rowH = max($rowH, max($v1Lines, $v2Lines) * $lineH + 2);

        $this->SetFont('Arial', 'B', 8);
        $this->Rect($x, $y, $w[0], $rowH); $this->SetXY($x + 1, $y + 1); $this->Cell($w[0] - 2, $lineH, $l1);
        $this->SetFont('Arial', '', 8);
        $this->Rect($x + $w[0], $y, $w[1], $rowH); $this->SetXY($x + $w[0] + 1, $y + 1); $this->MultiCell($w[1] - 2, $lineH, $v1Wrapped);
        if ($l2 !== null) {
            $this->SetFont('Arial', 'B', 8);
            $this->Rect($x + $w[0] + $w[1], $y, $w[2], $rowH); $this->SetXY($x + $w[0] + $w[1] + 1, $y + 1); $this->Cell($w[2] - 2, $lineH, $l2);
            $this->SetFont('Arial', '', 8);
            $this->Rect($x + $w[0] + $w[1] + $w[2], $y, $w[3], $rowH); $this->SetXY($x + $w[0] + $w[1] + $w[2] + 1, $y + 1); $this->MultiCell($w[3] - 2, $lineH, $v2Wrapped);
        } else {
            $this->Rect($x + $w[0] + $w[1], $y, $w[2] + $w[3], $rowH);
        }
        $this->SetXY($x, $y + $rowH);
    }
}

require_login();
$org = require_org();
require_module_access('manufaktur_penawaran');

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    'SELECT h.*, c.name AS vendor_name, c.address, u.name AS created_by_name
     FROM manufaktur_penawaran h JOIN contacts c ON c.id=h.vendor_id LEFT JOIN users u ON u.id=h.created_by
     WHERE h.id=? AND h.organization_id=?'
);
$stmt->execute([$id, $org['organization_id']]);
$h = $stmt->fetch();
if (!$h) { http_response_code(404); exit('Form Penawaran Harga tidak ditemukan.'); }

$projName = null;
if ($h['project_id']) {
    $pjStmt = $pdo->prepare('SELECT name FROM projects WHERE id=?');
    $pjStmt->execute([$h['project_id']]);
    $projName = $pjStmt->fetch()['name'] ?? null;
}

$lines = $pdo->prepare('SELECT * FROM manufaktur_penawaran_lines WHERE manufaktur_penawaran_id=?');
$lines->execute([$id]);
$lines = $lines->fetchAll();

$priceStmt = $pdo->prepare('SELECT * FROM manufaktur_penawaran_line_prices WHERE line_id=?');
$attStmt = $pdo->prepare("SELECT file_path, original_name FROM manufaktur_penawaran_line_attachments WHERE line_id=? AND source='mj' ORDER BY id");
$allImages = [];
$allPdfFiles = [];
foreach ($lines as &$l) {
    $priceStmt->execute([$l['id']]);
    $prices = [];
    foreach ($priceStmt->fetchAll() as $pr) {
        $prices[$pr['price_type']] = (float) $pr['price_value'];
    }
    $l['price_map'] = $prices;
    $l['price_total'] = array_sum($prices);
    $l['grand_total'] = $l['price_total'] * (float) $l['qty'];

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

function tgl_indo_mp_pdf(?string $dateStr): string
{
    if (!$dateStr) return '—';
    $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $ts = strtotime($dateStr);
    return (int) date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}
function tgl_short_mp_pdf(?string $dateStr): string
{
    if (!$dateStr) return '—';
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $ts = strtotime($dateStr);
    return (int) date('j', $ts) . '-' . $bulan[(int) date('n', $ts)] . '-' . date('y', $ts);
}

$pdf = new ManufakturPenawaranPdf('P', 'mm', 'A4');
$pdf->SetMargins(15, 12, 15);
$pdf->SetAutoPageBreak(true, 15);

foreach ($lines as $l) {
    $pdf->AddPage();
    $pdf->Banner('Form Purchase Order + Penawaran Harga', 'MJ - MMT Standard Quotation Form');

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(90, 6, 'Tanggal : ' . mb_strtoupper(tgl_indo_mp_pdf($h['tanggal'])), 0, 0);
    $pdf->Cell(90, 6, 'No. Form Purchase Order : ' . ($h['po_number'] ?: '-'), 0, 1, 'R');
    $pdf->Cell(90, 6, 'Ketentuan DP : ' . ($h['dp_terms'] ?: 'DP 50%'), 0, 1);
    $pdf->Ln(1);

    $pdf->SectionBar('1. Informasi & Spesifikasi Barang (MJ)');
    $qtyTxt = rtrim(rtrim(number_format((float) $l['qty'], 2, ',', '.'), '0'), ',') . ' UNITS';
    $pdf->SpecRow('1. Nama Barang', $l['product_name_snapshot'], 'Kode Barang', $l['item_code'] ?: '-');
    $pdf->SpecRow('2. Ukuran (mm)', $l['size_mm'] ?: '-', 'Tekstur + Top Coat', $l['texture_topcoat'] ?: '-');
    $pdf->SpecRow('3. Finishing (Opsi)', $l['finishing_snapshot'] ?: '-', '9. Remark', $l['keterangan_mj'] ?: '-');
    $pdf->SpecRow('4. Jumlah (Qty)', $qtyTxt);
    $pdf->SpecRow('5. Material 1', $l['material_snapshot'] ?: '-');
    $pdf->SpecRow('6. Material 2', $l['material2_snapshot'] ?: '-');
    $pdf->SpecRow('7. Wood', $l['wood'] ?: '-');
    $pdf->SpecRow('8. Deadline', $l['deadline_mj'] ? mb_strtoupper(tgl_short_mp_pdf($l['deadline_mj'])) : '-');
    $pdf->SpecRow('10. Project / Cust.', $projName ?: '-');
    $pdf->Ln(2);

    $pdf->SectionBar('2. Rincian Harga & Timeline (MMT)', 'No. Form Penawaran Harga: ' . $h['doc_number']);
    $colW = [63, 63, 54];
    $pdf->SetFont('Arial', 'B', 7.5);
    $pdf->Cell($colW[0], 6, 'Rincian Komponen Harga', 1, 0, 'C');
    $pdf->Cell($colW[1], 6, 'Detail Spesifikasi', 1, 0, 'C');
    $pdf->Cell($colW[2], 6, 'Biaya (Rp) / Unit', 1, 1, 'C');
    $pdf->SetFont('Arial', '', 8);
    foreach (MPPDF_PRICE_CATEGORY_SPEC as $key => $cat) {
        $pdf->Cell($colW[0], 6, $cat['label'], 1);
        $pdf->Cell($colW[1], 6, $cat['detail'], 1);
        $pdf->Cell($colW[2], 6, number_format($l['price_map'][$key] ?? 0, 0, ',', '.'), 1, 1, 'R');
    }
    if (!empty($l['price_map']['harga_unit'])) {
        $pdf->Cell($colW[0], 6, 'Harga Unit (lama)', 1);
        $pdf->Cell($colW[1], 6, '-', 1);
        $pdf->Cell($colW[2], 6, number_format($l['price_map']['harga_unit'], 0, ',', '.'), 1, 1, 'R');
    }
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell($colW[0] + $colW[1], 6, 'TOTAL HARGA / Unit', 1);
    $pdf->Cell($colW[2], 6, number_format($l['price_total'], 0, ',', '.'), 1, 1, 'R');
    $pdf->SetFont('Arial', 'I', 7);
    $pdf->Cell($colW[0] + $colW[1] + $colW[2], 4, '(dikalikan dengan jumlah qty)', 0, 1, 'R');

    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetLineWidth(0.5);
    $pdf->Cell($colW[0], 7, 'GRAND TOTAL', 1, 0, '');
    $pdf->Cell($colW[1], 7, number_format($l['price_total'], 0, ',', '.') . ' x ' . $qtyTxt, 1, 0, 'R');
    $pdf->Cell($colW[2], 7, number_format($l['grand_total'], 0, ',', '.'), 1, 1, 'R');
    $pdf->SetLineWidth(0.2);

    $pdf->Cell($colW[0], 6, '8. Timeline Selesai', 1);
    $pdf->Cell($colW[1] + $colW[2], 6, $l['timeline_pabrik'] ? tgl_short_mp_pdf($l['timeline_pabrik']) : '-', 1, 1);

    $pdf->SetFont('Arial', 'I', 7.5);
    $pdf->Cell(0, 5, '(Estimasi ... Hari Kerja (Mulai dari Approval Gambar & ' . ($h['dp_terms'] ?: 'DP 50%') . '))', 0, 1, 'R');
    if ($l['remark_pabrik']) {
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(0, 5, 'Remark Manufaktur: ' . $l['remark_pabrik'], 0, 1);
    }
    $pdf->Ln(8);

    $sigY = $pdf->GetY();
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetXY(15, $sigY);
    $pdf->Cell(85, 5, 'Dibuat Oleh,', 0, 0, 'L');
    $pdf->SetXY(110, $sigY);
    $pdf->Cell(85, 5, 'Disetujui Oleh (Pelanggan),', 0, 0, 'L');
    $pdf->Line(15, $sigY + 25, 100, $sigY + 25);
    $pdf->Line(110, $sigY + 25, 195, $sigY + 25);
    $pdf->SetXY(110, $sigY + 26);
    $pdf->Cell(85, 5, '( ' . $h['vendor_name'] . ' )', 0, 0, 'C');

    $pdf->SetY($sigY + 36);
    $pdf->SetFont('Arial', 'BI', 7.5);
    $pdf->Cell(70, 5, '*Attachment file PDF / JPG', 1, 1, 'L');
    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell(0, 5, date('d-m-y'), 0, 1, 'C');
}

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
        continue;
    }
}

$pdf->Output('I', 'Form-Penawaran-Harga-' . str_replace('/', '-', $h['doc_number']) . '.pdf');
