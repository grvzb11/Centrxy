<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use ZipArchive;

/** Bounded first-column import; XLSX is read in-process without external services. */
class RecipientImportService
{
    public function names(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['csv', 'txt', 'xlsx'], true)) {
            throw new RuntimeException('Choose a CSV, TXT, or XLSX file.');
        }
        if ($file->getSize() > 25 * 1024 * 1024) {
            throw new RuntimeException('Import files must be 25 MB or smaller.');
        }
        $names = $extension === 'xlsx' ? $this->fromExcel($file->getRealPath()) : $this->fromCsv($file->getRealPath());
        if (in_array(mb_strtolower(trim($names[0] ?? '')), ['name','recipient','recipient_name','full_name','full name'], true)) {
            array_shift($names);
        }
        $names = array_values(array_filter(array_map(fn ($v) => trim(preg_replace('/\s+/u', ' ', (string) $v) ?? ''), $names), fn ($v) => $v !== ''));
        if (count($names) > (int) config('certificates.max_import_rows', 1000)) {
            throw new RuntimeException('The file contains more than the permitted number of recipients.');
        }
        foreach ($names as $name) {
            if (mb_strlen($name) > 180 || preg_match('/[\x00-\x1F\x7F]/', $name)) {
                throw new RuntimeException('An imported name exceeds 180 characters or contains invalid control characters.');
            }
        }
        return $names;
    }

    private function fromCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if (!$handle) throw new RuntimeException('Could not open the file.');
        $rows = [];
        try {
            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $rows[] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($row[0] ?? ''));
                if (count($rows) > (int) config('certificates.max_import_rows',1000) + 2) break;
            }
        } finally { fclose($handle); }
        return $rows;
    }

    private function fromExcel(string $path): array
    {
        if (!class_exists(ZipArchive::class) || !class_exists(\DOMDocument::class)) {
            throw new RuntimeException('Enable PHP extensions zip and dom to import Excel workbooks.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new RuntimeException('Could not read the XLSX workbook.');
        try {
            $shared = [];
            $sharedXml = $this->safePart($zip, 'xl/sharedStrings.xml', 16 * 1024 * 1024, false);
            if ($sharedXml !== null) {
                $xpath = $this->xml($sharedXml);
                foreach ($xpath->query('/*[local-name()="sst"]/*[local-name()="si"]') as $si) {
                    $value = '';
                    foreach ($xpath->query('.//*[local-name()="t"]', $si) as $text) $value .= $text->textContent;
                    $shared[] = $value;
                    if (count($shared) > 100000) throw new RuntimeException('The XLSX string table is too large.');
                }
            }
            // The first worksheet in common spreadsheets is sheet1.xml. If renamed,
            // derive its safe internal path through the workbook relationship ID.
            $sheet = 'xl/worksheets/sheet1.xml';
            $book = $this->safePart($zip, 'xl/workbook.xml', 2 * 1024 * 1024, false);
            $rels = $this->safePart($zip, 'xl/_rels/workbook.xml.rels', 2 * 1024 * 1024, false);
            if ($book !== null && $rels !== null) {
                $wx = $this->xml($book);
                $first = $wx->query('//*[local-name()="sheets"]/*[local-name()="sheet"]')->item(0);
                $relationId = $first?->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
                if ($relationId) {
                    $rx = $this->xml($rels);
                    foreach ($rx->query('//*[local-name()="Relationship"]') as $rel) {
                        if ($rel->getAttribute('Id') === $relationId) {
                            $target = ltrim($rel->getAttribute('Target'), '/');
                            if (str_starts_with($target, 'xl/')) $sheet = $target;
                            elseif (str_starts_with($target, 'worksheets/')) $sheet = 'xl/'.$target;
                            break;
                        }
                    }
                }
            }
            if (!preg_match('#^xl/worksheets/[A-Za-z0-9_-]+\.xml$#D', $sheet)) throw new RuntimeException('The first worksheet location is invalid.');
            $worksheet = $this->safePart($zip, $sheet, 18 * 1024 * 1024);
            $xml = $this->xml($worksheet);
            $names = [];
            $max = (int) config('certificates.max_import_rows',1000) + 3;
            foreach ($xml->query('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row) {
                $value = '';
                foreach ($xml->query('./*[local-name()="c"]', $row) as $cell) {
                    $ref = strtoupper($cell->getAttribute('r'));
                    if ($ref !== '' && !preg_match('/^A[0-9]+$/', $ref)) continue;
                    $type = $cell->getAttribute('t');
                    if ($type === 'inlineStr') {
                        foreach ($xml->query('.//*[local-name()="t"]', $cell) as $part) $value .= $part->textContent;
                    } else {
                        $node = $xml->query('./*[local-name()="v"]', $cell)->item(0);
                        $raw = $node?->textContent ?? '';
                        $value = $type === 's' ? ($shared[(int) $raw] ?? '') : $raw;
                    }
                    break;
                }
                if ($value !== '') $names[] = $value;
                if (count($names) > $max) break;
            }
            return $names;
        } finally { $zip->close(); }
    }

    private function safePart(ZipArchive $zip, string $name, int $limit, bool $required = true): ?string
    {
        $index = $zip->locateName($name);
        if ($index === false) {
            if ($required) throw new RuntimeException('The XLSX workbook is missing its first worksheet.');
            return null;
        }
        $stat = $zip->statIndex($index);
        if (($stat['size'] ?? 0) > $limit) throw new RuntimeException('XLSX worksheet data exceeds the safe processing limit.');
        $content = $zip->getFromIndex($index);
        if (!is_string($content) || strlen($content) > $limit) throw new RuntimeException('Could not safely read the XLSX worksheet.');
        return $content;
    }

    private function xml(string $contents): \DOMXPath
    {
        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            if (!$doc->loadXML($contents, LIBXML_NONET)) throw new RuntimeException('An XLSX worksheet contains invalid XML.');
        } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        return new \DOMXPath($doc);
    }
}
