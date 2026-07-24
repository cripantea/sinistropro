<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AiFieldExtractorService
{
    private const MODEL_HAIKU  = 'claude-3-5-haiku-20241022';
    private const MODEL_SONNET = 'claude-3-5-sonnet-20241022';
    private const MODEL        = self::MODEL_SONNET;

    private const API_URL        = 'https://api.anthropic.com/v1/messages';
    private const API_VER        = '2023-06-01';
    private const PDF_BETA       = 'pdfs-2024-09-25';
    private const MAX_TOKENS     = 4096;
    private const MAX_PDF_BYTES  = 25 * 1024 * 1024;

    private const SYSTEM_PROMPT = <<<'PROMPT'
You are a precise PDF form-field extractor with advanced spatial analysis capabilities. Your task is to identify every fillable field in the provided PDF form AND determine the exact position of each field's input area on the page.

RESPONSE FORMAT RULES (non-negotiable):
1. Respond with ONLY a valid JSON array. The response MUST start with [ and end with ].
2. Do NOT include markdown code fences (```), explanatory text, or any character outside the JSON array.
3. Each element must be a JSON object with these keys ("h" only for "textarea" fields, otherwise omit it):
   - "name"     : identifier in strict snake_case (lowercase ASCII letters, digits, underscores only)
   - "label"    : human-readable Italian label as it appears in the form
   - "type"     : one of exactly "text", "textarea", "date", or "number"
   - "required" : boolean — true if marked as mandatory
   - "page"     : integer — 1-indexed page number where the field appears
   - "x"        : float 0.0–100.0 — horizontal start of the input area as % of page width (0 = left edge)
   - "y"        : float 0.0–100.0 — vertical start of the input area as % of page height (0 = top edge, 100 = bottom)
   - "w"        : float 0.0–100.0 — estimated width of the input area as % of page width
   - "h"        : float 0.0–100.0 — ONLY for "textarea" fields: estimated height of the blank area as % of page height

TYPE RULES:
- Use "textarea" for large multi-line blank areas meant for free-form text: a big empty box, several blank ruled lines stacked together, or a field labeled "Descrizione", "Note", "Osservazioni", "Motivazione", "Dettagli" and similar, where the visible blank space spans more than one line of text.
- Use "text" for single-line blanks or underlines, even if the label suggests a short free-text answer.

COORDINATE RULES:
- x and y must point to the exact start of the blank space, dotted line, or underline where the user would write the value — NOT to the label text.
- y = 0.0 means the very top of the page; y = 100.0 means the very bottom.
- x = 0.0 means the very left edge; x = 100.0 means the very right edge.
- For a field label on the left and blank space on the right (e.g. "Nome: ___________"), x should be the position right after the colon/space, not at the label start.
- w should reflect the actual visible width of the blank space or underline. If no explicit line is visible, estimate a reasonable input width (typically 15–40% of page width depending on the field).
- For "textarea" fields, h should reflect the actual visible height of the whole blank area (from its top edge to its bottom edge), not a single line.
- Be precise: a ±2% error in coordinates is acceptable; a ±10% error is not.

SNAKE_CASE RULES for "name":
- Transliterate accented characters (è→e, à→a, ò→o, etc.)
- Replace spaces and non-alphanumeric characters with underscores
- Remove leading/trailing underscores
- Example: "Nome e Cognome" → "nome_e_cognome", "Importo €" → "importo"

EXAMPLE of the ONLY acceptable response format:
[{"name":"nome_cognome","label":"Nome e Cognome","type":"text","required":true,"page":1,"x":35.5,"y":18.2,"w":30.0},{"name":"data_sinistro","label":"Data del Sinistro","type":"date","required":true,"page":1,"x":72.0,"y":18.2,"w":18.0},{"name":"importo_danno","label":"Importo del Danno €","type":"number","required":false,"page":2,"x":50.0,"y":45.0,"w":20.0},{"name":"descrizione_danno","label":"Descrizione del Danno","type":"textarea","required":false,"page":2,"x":10.0,"y":55.0,"w":80.0,"h":18.0}]
PROMPT;

    /**
     * Download a PDF from S3, send it to Claude via the native document block,
     * and return the extracted fields schema with spatial coordinates.
     *
     * @return array<int, array{name: string, label: string, type: string, required: bool, page: int, x: float, y: float, w: float, h?: float}>
     * @throws RuntimeException
     */
    public function extractFromPdf(string $s3Key): array
    {
        $apiKey = config('services.anthropic.key');

        if (empty($apiKey)) {
            throw new RuntimeException(
                'Anthropic API key non configurata. Aggiungi ANTHROPIC_API_KEY al file .env.'
            );
        }

        try {
            $pdfContent = Storage::disk('s3')->get($s3Key);
        } catch (\Throwable $e) {
            Log::error('AiFieldExtractorService: S3 download failed', [
                'key'   => $s3Key,
                'error' => $e->getMessage(),
            ]);
            throw new RuntimeException(
                "Impossibile scaricare il PDF da S3 (chiave: {$s3Key}): {$e->getMessage()}"
            );
        }

        if ($pdfContent === null || $pdfContent === false) {
            throw new RuntimeException("Il file PDF non esiste su S3 (chiave: {$s3Key}).");
        }

        if (strlen($pdfContent) > self::MAX_PDF_BYTES) {
            $mb = round(strlen($pdfContent) / 1024 / 1024, 1);
            throw new RuntimeException("Il PDF è troppo grande ({$mb} MB). Limite massimo: 25 MB.");
        }

        $base64Pdf = base64_encode($pdfContent);
        $model     = config('services.anthropic.model', self::MODEL);

        $response = Http::withHeaders([
            'x-api-key'         => $apiKey,
            'anthropic-version' => self::API_VER,
            'anthropic-beta'    => self::PDF_BETA,
        ])
        ->timeout(120)
        ->post(self::API_URL, [
            'model'      => $model,
            'max_tokens' => self::MAX_TOKENS,
            'system'     => self::SYSTEM_PROMPT,
            'messages'   => [
                [
                    'role'    => 'user',
                    'content' => [
                        [
                            'type'   => 'document',
                            'source' => [
                                'type'       => 'base64',
                                'media_type' => 'application/pdf',
                                'data'       => $base64Pdf,
                            ],
                        ],
                        [
                            'type' => 'text',
                            'text' => 'Analizza questo modulo PDF. Per ogni campo compilabile estrai nome, etichetta, tipo, obbligatorietà e le coordinate spaziali precise (x, y, w, page, e h per i campi multiriga "textarea"). Rispondi ESCLUSIVAMENTE con l\'array JSON richiesto — nessun altro testo.',
                        ],
                    ],
                ],
            ],
        ]);

        if (! $response->successful()) {
            $status = $response->status();
            $errMsg = $response->json('error.message') ?? $response->body();

            Log::error('AiFieldExtractorService: Anthropic API error', [
                'status' => $status,
                'error'  => $errMsg,
                's3_key' => $s3Key,
            ]);

            throw new RuntimeException("Errore dall'API Anthropic (HTTP {$status}): {$errMsg}");
        }

        $rawText = $response->json('content.0.text') ?? '';

        Log::info('AiFieldExtractorService: raw response received', [
            'model'  => $model,
            'length' => strlen($rawText),
        ]);

        return $this->parseFields($rawText);
    }

    /**
     * @return array<int, array{name: string, label: string, type: string, required: bool, page: int, x: float, y: float, w: float, h?: float}>
     * @throws RuntimeException
     */
    private function parseFields(string $raw): array
    {
        $cleaned = trim($raw);
        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/\s*```\s*$/i', '', $cleaned) ?? $cleaned;
        $cleaned = trim($cleaned);

        $decoded = json_decode($cleaned, true);

        if (! is_array($decoded)) {
            Log::warning('AiFieldExtractorService: JSON parse failed', [
                'raw_excerpt' => substr($raw, 0, 500),
            ]);
            throw new RuntimeException(
                'La risposta di Claude non contiene un JSON valido. Riprova o verifica il PDF.'
            );
        }

        $allowedTypes = ['text', 'textarea', 'date', 'number'];
        $fields       = [];

        foreach ($decoded as $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = $this->toSnakeCase((string) ($item['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $type = in_array($item['type'] ?? '', $allowedTypes, true)
                ? (string) $item['type']
                : 'text';

            $field = [
                'name'     => $name,
                'label'    => trim((string) ($item['label'] ?? $name)),
                'type'     => $type,
                'required' => (bool) ($item['required'] ?? false),
                'page'     => max(1, (int) ($item['page'] ?? 1)),
                'x'        => $this->clampCoord($item['x'] ?? 0),
                'y'        => $this->clampCoord($item['y'] ?? 0),
                'w'        => $this->clampCoord($item['w'] ?? 20),
            ];

            if ($type === 'textarea') {
                $field['h'] = $this->clampCoord($item['h'] ?? 8);
            }

            $fields[] = $field;
        }

        if (empty($fields)) {
            throw new RuntimeException(
                'Nessun campo estratto dal PDF. Il documento potrebbe non contenere campi compilabili riconoscibili da Claude.'
            );
        }

        return $fields;
    }

    private function clampCoord(mixed $value): float
    {
        $f = (float) $value;
        return round(max(0.0, min(100.0, $f)), 2);
    }

    private function toSnakeCase(string $input): string
    {
        $out = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $input);
        $out = ($out !== false && $out !== '') ? $out : $input;
        $out = strtolower($out);
        $out = preg_replace('/[^a-z0-9]+/', '_', $out) ?? $out;
        return trim($out, '_');
    }
}
