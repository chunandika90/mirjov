<?php
/**
 * Pembaca .xlsx MINIMAL — pasangan dari MinimalXlsxWriter (xlsx_writer.php).
 * Cukup buat baca balik file yang di-generate MinimalXlsxWriter ATAU yang
 * abis dibuka+disave ulang di Excel beneran (Excel otomatis ngubah inline
 * string jadi sharedStrings.xml pas nyimpen, makanya kedua format didukung).
 * Ambil sheet PERTAMA doang, balikin array baris (tiap baris = array asosiatif
 * kolom-letter => value string, cth. ['A'=>'MJT','B'=>'Kategori',...]).
 */
function read_xlsx_rows(string $path): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('File .xlsx gak bisa dibuka (bukan file Excel valid).');
    }

    $sharedStrings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $sst = @simplexml_load_string($sharedXml);
        if ($sst !== false) {
            foreach ($sst->si as $si) {
                // <si><t>text</t></si> ATAU rich text <si><r><t>a</t></r><r><t>b</t></r></si>
                if (isset($si->t)) {
                    $sharedStrings[] = (string) $si->t;
                } else {
                    $text = '';
                    foreach ($si->r as $run) $text .= (string) $run->t;
                    $sharedStrings[] = $text;
                }
            }
        }
    }

    // Cari worksheet sheet PERTAMA lewat workbook.xml + rels (biar gak asumsi selalu sheet1.xml).
    $sheetPath = 'xl/worksheets/sheet1.xml';
    $workbookXml = $zip->getFromName('xl/workbook.xml');
    $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($workbookXml !== false && $relsXml !== false) {
        $wb = @simplexml_load_string($workbookXml);
        $rels = @simplexml_load_string($relsXml);
        if ($wb !== false && $rels !== false && isset($wb->sheets->sheet[0])) {
            $wbNs = $wb->sheets->sheet[0]->attributes('r', true);
            $rId = (string) $wbNs['id'];
            foreach ($rels->Relationship as $rel) {
                if ((string) $rel['Id'] === $rId) {
                    $sheetPath = 'xl/' . ltrim((string) $rel['Target'], '/');
                    break;
                }
            }
        }
    }

    $sheetXml = $zip->getFromName($sheetPath);
    $zip->close();
    if ($sheetXml === false) {
        throw new RuntimeException('Sheet di dalam file .xlsx gak ketemu.');
    }

    $sheet = @simplexml_load_string($sheetXml);
    if ($sheet === false) {
        throw new RuntimeException('Isi file .xlsx gak valid/rusak.');
    }

    $rows = [];
    if (!isset($sheet->sheetData->row)) return $rows;
    foreach ($sheet->sheetData->row as $row) {
        $rowData = [];
        foreach ($row->c as $c) {
            $ref = (string) $c['r'];
            preg_match('/^([A-Z]+)/', $ref, $m);
            $colLetter = $m[1] ?? '';
            $type = (string) $c['t'];
            if ($type === 's') {
                $idx = (int) $c->v;
                $value = $sharedStrings[$idx] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = isset($c->is->t) ? (string) $c->is->t : '';
            } elseif ($type === 'str') {
                $value = (string) $c->v;
            } else {
                $value = (string) $c->v;
            }
            if ($colLetter !== '') $rowData[$colLetter] = $value;
        }
        $rows[] = $rowData;
    }
    return $rows;
}

/**
 * Ubah baris hasil read_xlsx_rows() jadi array asosiatif per baris data,
 * pakai baris pertama sebagai header (cari kolom by NAMA, bukan posisi —
 * biar gak gampang rusak kalau urutan kolom template berubah lagi nanti).
 * Balikinnya: ['header'=>['nama produk'=>'A', 'qty'=>'F', ...], 'rows'=>[...]]
 */
function xlsx_rows_to_named(array $rows): array
{
    if (!$rows) return ['header' => [], 'rows' => []];
    $headerRow = array_shift($rows);
    $header = [];
    foreach ($headerRow as $col => $label) {
        $key = mb_strtolower(trim((string) $label));
        if ($key !== '') $header[$key] = $col;
    }
    $named = [];
    foreach ($rows as $r) {
        $entry = [];
        foreach ($header as $key => $col) {
            $entry[$key] = trim((string) ($r[$col] ?? ''));
        }
        // Skip baris yang bener-bener kosong semua.
        if (implode('', $entry) === '') continue;
        $named[] = $entry;
    }
    return ['header' => $header, 'rows' => $named];
}
