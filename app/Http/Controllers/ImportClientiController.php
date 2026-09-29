<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ImportClientiController extends Controller
{
    private const ALLOWED_EXTENSIONS = ['csv', 'json', 'xml'];
    private const MAX_FILE_MB        = 5;
    private const PREVIEW_ROWS       = 5;

    public function create(): Response
    {
        $schema = TenantContext::tenant()?->getClienteCustomFieldsSchema() ?? [];

        return Inertia::render('Clienti/Import', [
            'schema' => $schema,
        ]);
    }

    /** Step 1: parse uploaded file, return headers + preview rows + temp file id. */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'file'   => ['required', 'file', 'max:' . (self::MAX_FILE_MB * 1024)],
            'format' => ['required', 'in:csv,json,xml'],
        ]);

        $file      = $request->file('file');
        $format    = $request->input('format');
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return response()->json(['error' => 'Formato file non supportato.'], 422);
        }

        $tempId   = (string) Str::uuid();
        $tempPath = "tmp/import-clienti/{$tempId}.{$extension}";

        Storage::disk('local')->put($tempPath, file_get_contents($file->getRealPath()));

        try {
            $rows = $this->parseFile(Storage::disk('local')->path($tempPath), $format);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($tempPath);
            return response()->json(['error' => 'Impossibile leggere il file: ' . $e->getMessage()], 422);
        }

        if (empty($rows)) {
            Storage::disk('local')->delete($tempPath);
            return response()->json(['error' => 'Il file è vuoto o non contiene righe valide.'], 422);
        }

        $headers = array_keys($rows[0]);
        $preview = array_slice($rows, 0, self::PREVIEW_ROWS);
        $total   = count($rows);

        return response()->json([
            'file_id' => $tempId,
            'format'  => $format,
            'headers' => $headers,
            'preview' => $preview,
            'total'   => $total,
        ]);
    }

    /** Step 2: import with column mapping. */
    public function execute(Request $request): JsonResponse
    {
        $request->validate([
            'file_id' => ['required', 'string', 'uuid'],
            'format'  => ['required', 'in:csv,json,xml'],
            'mapping' => ['required', 'array'],
        ]);

        $tempPath = "tmp/import-clienti/{$request->file_id}.{$request->format}";

        if (! Storage::disk('local')->exists($tempPath)) {
            return response()->json(['error' => 'File temporaneo non trovato. Ricarica il file.'], 422);
        }

        try {
            $rows = $this->parseFile(Storage::disk('local')->path($tempPath), $request->input('format'));
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Errore nella lettura del file: ' . $e->getMessage()], 422);
        }

        $mapping = $request->input('mapping'); // ['Col A' => 'nome', 'Col B' => 'telefono', ...]

        $tenantId    = TenantContext::id();
        $created     = 0;
        $updated     = 0;
        $skipped     = 0;
        $errors      = [];

        foreach ($rows as $i => $row) {
            $rowNum = $i + 2;

            try {
                $data         = $this->mapRow($row, $mapping);
                $customFields = $data['custom_fields'] ?? [];
                unset($data['custom_fields']);

                if (empty($data['nome'])) {
                    $skipped++;
                    continue;
                }

                // Upsert by telefono if present, otherwise always create
                $existing = null;
                if (! empty($data['telefono'])) {
                    $existing = Cliente::acrossAllTenants()
                        ->where('tenant_id', $tenantId)
                        ->where('telefono', $data['telefono'])
                        ->first();
                }

                if ($existing) {
                    $existing->update(array_merge($data, [
                        'custom_fields' => array_merge($existing->custom_fields ?? [], $customFields),
                    ]));
                    $updated++;
                } else {
                    Cliente::create(array_merge($data, [
                        'tenant_id'     => $tenantId,
                        'custom_fields' => $customFields ?: null,
                    ]));
                    $created++;
                }
            } catch (\Throwable $e) {
                $errors[] = "Riga {$rowNum}: " . $e->getMessage();
            }
        }

        Storage::disk('local')->delete($tempPath);

        return response()->json([
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors'  => $errors,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function parseFile(string $path, string $format): array
    {
        return match ($format) {
            'csv'  => $this->parseCsv($path),
            'json' => $this->parseJson($path),
            'xml'  => $this->parseXml($path),
            default => throw new \InvalidArgumentException("Formato {$format} non supportato."),
        };
    }

    private function parseCsv(string $path): array
    {
        $rows    = [];
        $headers = null;

        $handle = fopen($path, 'r');
        if (! $handle) throw new \RuntimeException('Impossibile aprire il file CSV.');

        // Detect BOM (UTF-8)
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);

        while (($line = fgetcsv($handle, 0, ',')) !== false) {
            if ($headers === null) {
                $headers = array_map('trim', $line);
                continue;
            }
            if (count($line) !== count($headers)) continue;
            $rows[] = array_combine($headers, array_map('trim', $line));
        }

        fclose($handle);
        return $rows;
    }

    private function parseJson(string $path): array
    {
        $content = file_get_contents($path);
        $data    = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        // Accept top-level array OR { "data": [...] }
        if (isset($data['data']) && is_array($data['data'])) {
            $data = $data['data'];
        }

        if (! is_array($data) || empty($data)) {
            throw new \RuntimeException('Il JSON deve essere un array di oggetti.');
        }

        // Normalize: all rows get the same keys
        $allKeys = [];
        foreach ($data as $row) {
            if (is_array($row)) $allKeys = array_unique(array_merge($allKeys, array_keys($row)));
        }

        return array_map(function ($row) use ($allKeys) {
            $out = [];
            foreach ($allKeys as $k) {
                $out[$k] = isset($row[$k]) ? (string) $row[$k] : '';
            }
            return $out;
        }, array_filter($data, 'is_array'));
    }

    private function parseXml(string $path): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($path);

        if ($xml === false) {
            $errors = implode(', ', array_map(fn ($e) => $e->message, libxml_get_errors()));
            throw new \RuntimeException("XML non valido: {$errors}");
        }

        $rows = [];

        // Support <root><item>...</item></root> OR flat <root><nome>...</nome></root>
        $children = $xml->children();

        if ($children->count() === 0) {
            return [];
        }

        // If first child has children, treat as list of records
        $firstChild = $children[0];
        if ($firstChild->children()->count() > 0) {
            foreach ($children as $child) {
                $row = [];
                foreach ($child->children() as $key => $value) {
                    $row[(string) $key] = (string) $value;
                }
                if (! empty($row)) $rows[] = $row;
            }
        } else {
            // Single record at root level
            $row = [];
            foreach ($xml->children() as $key => $value) {
                $row[(string) $key] = (string) $value;
            }
            if (! empty($row)) $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Maps a source row to Cliente fields using the user-provided mapping.
     * Mapping: ['SourceCol' => 'nome' | 'telefono' | 'email' | 'custom_fields.field_name']
     */
    private function mapRow(array $row, array $mapping): array
    {
        $result       = [];
        $customFields = [];

        foreach ($mapping as $sourceCol => $dest) {
            if (empty($dest) || ! isset($row[$sourceCol])) continue;

            $value = trim((string) $row[$sourceCol]);

            if (str_starts_with($dest, 'custom_fields.')) {
                $fieldName = substr($dest, strlen('custom_fields.'));
                if ($value !== '') {
                    $customFields[$fieldName] = $value;
                }
            } else {
                $result[$dest] = $value !== '' ? $value : null;
            }
        }

        if (! empty($customFields)) {
            $result['custom_fields'] = $customFields;
        }

        return $result;
    }
}
