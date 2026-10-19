<?php

namespace App\Services\ImportClienti;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Legge un file di clienti (Excel, CSV, JSON, XML) e lo riduce a una tabella:
 * intestazioni + righe come array [intestazione => valore stringa].
 *
 * JSON e XML annidati vengono appiattiti: gli oggetti diventano colonne "a.b",
 * una lista di oggetti (es. i veicoli di un cliente) diventa una riga per elemento,
 * con i dati del cliente ripetuti — la stessa forma di un Excel "una riga per veicolo".
 */
class ImportFileReader
{
    public const EXTENSIONS = ['xls', 'xlsx', 'ods', 'csv', 'txt', 'json', 'xml'];

    /** @return array{headers: array<int, string>, rows: array<int, array<string, string>>} */
    public function read(string $path, string $extension): array
    {
        $records = match (strtolower($extension)) {
            'xls', 'xlsx', 'ods' => $this->readSpreadsheet($path),
            'csv', 'txt'         => $this->readCsv($path),
            'json'               => $this->readJson($path),
            'xml'                => $this->readXml($path),
            default              => throw new RuntimeException("Formato .{$extension} non supportato."),
        };

        return $records;
    }

    // ── Tabellari ────────────────────────────────────────────────────────────

    private function readSpreadsheet(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        $sheet = $reader->load($path)->getActiveSheet();

        $table = [];
        foreach ($sheet->getRowIterator() as $row) {
            $cells = $row->getCellIterator();
            $cells->setIterateOnlyExistingCells(false);
            $values = [];
            foreach ($cells as $cell) {
                $values[] = $this->scalar($cell->getValue());
            }
            $table[] = $values;
        }

        return $this->fromTable($table);
    }

    private function readCsv(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        // Excel in italiano esporta con ";": si sceglie il separatore più presente nella prima riga.
        $firstLine = strtok($content, "\r\n") ?: '';
        $delimiter = collect([';', ',', "\t", '|'])
            ->sortByDesc(fn ($d) => substr_count($firstLine, $d))
            ->first();

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $table = [];
        while (($line = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $table[] = array_map(fn ($v) => $this->scalar($v), $line);
        }
        fclose($handle);

        return $this->fromTable($table);
    }

    /** Prima riga non vuota = intestazioni; le righe completamente vuote si saltano. */
    private function fromTable(array $table): array
    {
        $headers = null;
        $rows = [];

        foreach ($table as $values) {
            if (implode('', $values) === '') {
                continue;
            }
            if ($headers === null) {
                $headers = $this->uniqueHeaders($values);
                continue;
            }
            $row = [];
            foreach ($headers as $i => $h) {
                if ($h !== null) {
                    $row[$h] = $values[$i] ?? '';
                }
            }
            $rows[] = $row;
        }

        return ['headers' => array_values(array_filter($headers ?? [], fn ($h) => $h !== null)), 'rows' => $rows];
    }

    /** Intestazioni vuote vengono ignorate, quelle ripetute ricevono un suffisso " (2)". */
    private function uniqueHeaders(array $values): array
    {
        $seen = [];
        return array_map(function ($h) use (&$seen) {
            $h = trim($h);
            if ($h === '') {
                return null;
            }
            $seen[$h] = ($seen[$h] ?? 0) + 1;
            return $seen[$h] > 1 ? "{$h} ({$seen[$h]})" : $h;
        }, $values);
    }

    // ── Strutturati ──────────────────────────────────────────────────────────

    private function readJson(string $path): array
    {
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return $this->fromRecords($this->findRecords($data));
    }

    private function readXml(string $path): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($path, options: LIBXML_NOCDATA | LIBXML_NONET);
        if ($xml === false) {
            $errors = implode(', ', array_map(fn ($e) => trim($e->message), libxml_get_errors()));
            libxml_clear_errors();
            throw new RuntimeException("XML non valido: {$errors}");
        }

        return $this->fromRecords($this->findRecords($this->xmlToArray($xml)));
    }

    /** Elemento XML → array; gli elementi ripetuti diventano liste. */
    private function xmlToArray(\SimpleXMLElement $el): array|string
    {
        $out = [];
        foreach ($el->attributes() as $k => $v) {
            $out[$k] = (string) $v;
        }
        foreach ($el->children() as $name => $child) {
            $value = $child->count() > 0 || $child->attributes()->count() > 0 ? $this->xmlToArray($child) : trim((string) $child);
            if (array_key_exists($name, $out)) {
                if (! is_array($out[$name]) || ! array_is_list($out[$name])) {
                    $out[$name] = [$out[$name]];
                }
                $out[$name][] = $value;
            } else {
                $out[$name] = $value;
            }
        }

        return $out === [] ? trim((string) $el) : $out;
    }

    /**
     * Trova la lista dei record: la radice se è già una lista, altrimenti la prima
     * lista di oggetti scendendo nei figli (es. {"data": [...]}, <clienti><cliente/>…).
     */
    private function findRecords(mixed $data, int $depth = 0): array
    {
        if (is_array($data) && array_is_list($data) && $data !== [] && is_array($data[0])) {
            return $data;
        }
        if (is_array($data) && $depth < 4) {
            foreach ($data as $value) {
                if (is_array($value)) {
                    $found = $this->findRecords($value, $depth + 1);
                    if ($found !== []) {
                        return $found;
                    }
                }
            }
            // Un solo record (oggetto con soli valori)
            if ($depth === 0 && ! array_is_list($data)) {
                return [$data];
            }
        }

        return [];
    }

    private function fromRecords(array $records): array
    {
        $headers = [];
        $rows = [];

        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }
            foreach ($this->flatten($record) as $row) {
                $headers += array_fill_keys(array_keys($row), true);
                $rows[] = $row;
            }
        }

        $headers = array_map('strval', array_keys($headers));
        $rows = array_map(fn ($r) => array_merge(array_fill_keys($headers, ''), $r), $rows);

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * Un record annidato → una o più righe piatte. La prima lista di oggetti
     * (es. "veicoli") moltiplica le righe; le liste di valori semplici si uniscono con ", ".
     *
     * @return array<int, array<string, string>>
     */
    private function flatten(array $record, string $prefix = ''): array
    {
        $base = [];
        $expand = null;

        foreach ($record as $key => $value) {
            $name = $prefix . $key;
            if (! is_array($value)) {
                $base[$name] = $this->scalar($value);
            } elseif (array_is_list($value) && $value !== [] && is_array($value[0])) {
                if ($expand === null) {
                    $expand = [$name, $value];
                }
            } elseif (array_is_list($value)) {
                $base[$name] = implode(', ', array_map(fn ($v) => $this->scalar($v), array_filter($value, fn ($v) => ! is_array($v))));
            } else {
                $nested = $this->flatten($value, $name . '.');
                $base += $nested[0] ?? [];
                if (count($nested) > 1 && $expand === null) {
                    $expand = [null, $nested];
                }
            }
        }

        if ($expand === null) {
            return [$base];
        }

        [$name, $items] = $expand;
        $rows = [];
        foreach ($items as $item) {
            $parts = $name === null ? [$item] : (is_array($item) ? $this->flatten($item, $name . '.') : [[]]);
            foreach ($parts as $part) {
                $rows[] = $base + $part;
            }
        }

        return $rows;
    }

    private function scalar(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? '1' : '0',
            is_float($value) && floor($value) === $value && abs($value) < 1e15 => (string) (int) $value,
            default => trim((string) $value),
        };
    }
}
