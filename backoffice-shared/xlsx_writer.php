<?php
/**
 * Penulis .xlsx MINIMAL — bukan library lengkap (gak ada dependency luar,
 * cukup ekstensi zip+gd bawaan PHP). Cukup buat 1 sheet: header bold, baris
 * data (angka/teks), dan opsional 1 foto per baris (kolom A, resize kecil via
 * GD biar file-nya gak berat). Dipakai spesifik buat Export Excel Laporan
 * Inventory — bukan dirancang generik buat kebutuhan lain.
 */
class MinimalXlsxWriter
{
    private array $rowsXml = [];
    private array $images = []; // ['row0'=>int, 'col0'=>int, 'path'=>string, 'wpx'=>int, 'hpx'=>int]
    private int $rowNum = 0;
    private array $colWidths;
    private float $imgRowHeightPt = 48;

    public function __construct(array $colWidthsChars)
    {
        $this->colWidths = $colWidthsChars;
    }

    private static function colLetter(int $idx0): string
    {
        $s = '';
        $n = $idx0 + 1;
        while ($n > 0) {
            $rem = ($n - 1) % 26;
            $s = chr(65 + $rem) . $s;
            $n = intdiv($n - 1, 26);
        }
        return $s;
    }

    private static function xmlEsc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    public function addHeaderRow(array $labels): void
    {
        $this->rowNum++;
        $cells = '';
        foreach ($labels as $i => $label) {
            $ref = self::colLetter($i) . $this->rowNum;
            $cells .= '<c r="' . $ref . '" t="inlineStr" s="1"><is><t>' . self::xmlEsc((string) $label) . '</t></is></c>';
        }
        $this->rowsXml[] = '<row r="' . $this->rowNum . '">' . $cells . '</row>';
    }

    /**
     * @param array $cells tiap elemen: ['type'=>'s'|'n','value'=>mixed]
     * @param string|null $imagePath path lokal foto (opsional)
     * @param int $imageCol kolom (0-based) tempat foto ditaro — default kolom A
     * @param int $imageMaxPx sisi terpanjang foto di-resize maksimal segini (px)
     */
    public function addDataRow(array $cells, ?string $imagePath = null, int $imageCol = 0, int $imageMaxPx = 60): void
    {
        $this->rowNum++;
        $rowAttrs = 'r="' . $this->rowNum . '"';
        $xmlCells = '';
        foreach ($cells as $i => $cell) {
            $ref = self::colLetter($i) . $this->rowNum;
            if ($cell['type'] === 'n') {
                $xmlCells .= '<c r="' . $ref . '"><v>' . (is_numeric($cell['value']) ? $cell['value'] : 0) . '</v></c>';
            } else {
                $xmlCells .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . self::xmlEsc((string) $cell['value']) . '</t></is></c>';
            }
        }
        if ($imagePath && is_file($imagePath)) {
            $info = @getimagesize($imagePath);
            if ($info) {
                $ratio = min($imageMaxPx / $info[0], $imageMaxPx / $info[1], 1);
                $hpx = max(1, (int) round($info[1] * $ratio));
                $rowHeightPt = max($this->imgRowHeightPt, $hpx * 0.8);
                $rowAttrs .= ' customHeight="1" ht="' . $rowHeightPt . '"';
                $this->images[] = [
                    'row0' => $this->rowNum - 1,
                    'col0' => $imageCol,
                    'path' => $imagePath,
                    'wpx' => max(1, (int) round($info[0] * $ratio)),
                    'hpx' => $hpx,
                ];
            }
        }
        $this->rowsXml[] = '<row ' . $rowAttrs . '>' . $xmlCells . '</row>';
    }

    private function buildCols(): string
    {
        $xml = '<cols>';
        foreach ($this->colWidths as $i => $w) {
            $xml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
        }
        $xml .= '</cols>';
        return $xml;
    }

    private function buildDrawingXml(): string
    {
        $anchors = '';
        foreach ($this->images as $i => $img) {
            $picId = $i + 1;
            $cxEmu = $img['wpx'] * 9525;
            $cyEmu = $img['hpx'] * 9525;
            $anchors .= '<xdr:oneCellAnchor>'
                . '<xdr:from><xdr:col>' . $img['col0'] . '</xdr:col><xdr:colOff>19050</xdr:colOff><xdr:row>' . $img['row0'] . '</xdr:row><xdr:rowOff>9525</xdr:rowOff></xdr:from>'
                . '<xdr:ext cx="' . $cxEmu . '" cy="' . $cyEmu . '"/>'
                . '<xdr:pic>'
                . '<xdr:nvPicPr><xdr:cNvPr id="' . ($picId + 1) . '" name="Picture ' . $picId . '"/><xdr:cNvPicPr/></xdr:nvPicPr>'
                . '<xdr:blipFill><a:blip r:embed="rId' . $picId . '"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
                . '<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cxEmu . '" cy="' . $cyEmu . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr>'
                . '</xdr:pic>'
                . '<xdr:clientData/>'
                . '</xdr:oneCellAnchor>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . $anchors . '</xdr:wsDr>';
    }

    private function buildDrawingRels(): string
    {
        // resizeImageData() SELALU nge-encode ulang jadi JPEG (biar kecil), jadi ekstensinya
        // di sini juga selalu .jpg — gak boleh ikutan ekstensi file sumber (bisa .png/.webp).
        $rels = '';
        foreach ($this->images as $i => $img) {
            $picId = $i + 1;
            $rels .= '<Relationship Id="rId' . $picId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/image' . $picId . '.jpg"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '</Relationships>';
    }

    /** Kirim file .xlsx langsung ke browser (header attachment) lalu selesai. */
    public function output(string $filename): void
    {
        $tmpPath = $this->buildZipToTemp();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmpPath));
        readfile($tmpPath);
        unlink($tmpPath);
    }

    /** Simpan ke path lokal (buat testing/reuse) — TIDAK ngirim header/output ke browser. */
    public function saveToFile(string $destPath): void
    {
        $tmpPath = $this->buildZipToTemp();
        rename($tmpPath, $destPath);
    }

    private function buildZipToTemp(): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        $zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $hasImages = count($this->images) > 0;

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Default Extension="jpeg" ContentType="image/jpeg"/><Default Extension="jpg" ContentType="image/jpeg"/><Default Extension="png" ContentType="image/png"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . ($hasImages ? '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>' : '')
            . '</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>');

        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
            . '</styleSheet>');

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . $this->buildCols()
            . '<sheetData>' . implode('', $this->rowsXml) . '</sheetData>'
            . ($hasImages ? '<drawing r:id="rIdDrawing1"/>' : '')
            . '</worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);

        if ($hasImages) {
            $zip->addFromString('xl/worksheets/_rels/sheet1.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rIdDrawing1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>'
                . '</Relationships>');
            $zip->addFromString('xl/drawings/drawing1.xml', $this->buildDrawingXml());
            $zip->addFromString('xl/drawings/_rels/drawing1.xml.rels', $this->buildDrawingRels());

            foreach ($this->images as $i => $img) {
                $picId = $i + 1;
                // Resize on the fly ke ukuran kecil biar file .xlsx-nya gak bengkak — selalu jadi .jpg.
                $resizedData = $this->resizeImageData($img['path'], $img['wpx'], $img['hpx']);
                $zip->addFromString('xl/media/image' . $picId . '.jpg', $resizedData);
            }
        }

        $zip->close();
        return $tmpPath;
    }

    private function resizeImageData(string $path, int $w, int $h): string
    {
        $info = @getimagesize($path);
        if (!$info) return (string) file_get_contents($path);
        $src = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
            default => null,
        };
        if (!$src) return (string) file_get_contents($path);
        $dst = imagecreatetruecolor($w, $h);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $info[0], $info[1]);
        ob_start();
        imagejpeg($dst, null, 80);
        $data = ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);
        return $data;
    }
}
