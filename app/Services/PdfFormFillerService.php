<?php

namespace App\Services;

use App\Models\Allegato;
use App\Models\PraticaModule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;

// fpdf/fpdf v1.86+ uses the Fpdf\Fpdf namespace; setasign/fpdi still expects
// the legacy global \FPDF class. Bridge them before FPDI is first loaded.
if (! class_exists('FPDF', false)) {
    class_alias(\Fpdf\Fpdf::class, 'FPDF');
}

class PdfFormFillerService
{
    /**
     * Overlays the module values onto the blank PDF matrix using geometric
     * coordinates (x%, y%, w% of A4 page), uploads the result to S3 and
     * creates the Allegato record.
     *
     * @return string  S3 key of the compiled PDF, or empty string on error
     */
    public function compile(PraticaModule $praticaModule): string
    {
        $praticaModule->loadMissing(['template', 'pratica']);

        $template = $praticaModule->template;
        $pratica  = $praticaModule->pratica;

        $inputTmp  = null;
        $compatTmp = null;
        $outputTmp = null;

        try {
            // ── 1. Validate ───────────────────────────────────────────────────
            if (! $template->pdf_template_s3_key) {
                throw new \RuntimeException(
                    "ModuleTemplate #{$template->id} ({$template->name}) non ha un PDF matrice configurato."
                );
            }

            // ── 2. Download PDF matrix from S3 ────────────────────────────────
            $pdfContent = Storage::disk('s3')->get($template->pdf_template_s3_key);

            if ($pdfContent === false || $pdfContent === null) {
                throw new \RuntimeException(
                    "PDF matrice non trovato su S3: {$template->pdf_template_s3_key}"
                );
            }

            $inputTmp  = tempnam(sys_get_temp_dir(), 'fpdi_in_');
            $outputTmp = $inputTmp . '_out.pdf';
            file_put_contents($inputTmp, $pdfContent);

            // ── 3. Downgrade to PDF 1.4 via Ghostscript ───────────────────────
            // FPDI free parser cannot read cross-reference streams (PDF 1.5+).
            // Ghostscript rewrites the file as PDF 1.4 with uncompressed xrefs.
            $compatTmp = $this->toCompatPdf($inputTmp);
            $sourceFile = $compatTmp ?? $inputTmp;

            // ── 4. Initialise FPDI (extends FPDF) ─────────────────────────────
            $pdf = new Fpdi('P', 'mm', 'A4');
            $pdf->SetAutoPageBreak(false);
            $pdf->SetMargins(0, 0, 0);

            $pageCount = $pdf->setSourceFile($sourceFile);

            // ── 4. Group fields by page ───────────────────────────────────────
            $schema = $template->fields_schema ?? [];
            $values = $praticaModule->values   ?? [];

            $fieldsByPage = [];
            foreach ($schema as $field) {
                $page = max(1, (int) ($field['page'] ?? 1));
                $fieldsByPage[$page][] = $field;
            }

            // ── 5. Loop through every page ────────────────────────────────────
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $tpl = $pdf->importPage($pageNo);

                // A4 portrait: 210 mm wide × 297 mm tall
                $pdf->AddPage('P', 'A4');

                // Stretch the imported page to fill the full A4 sheet
                $pdf->useTemplate($tpl, 0, 0, 210, 297);

                $fields = $fieldsByPage[$pageNo] ?? [];

                if (empty($fields)) {
                    continue;
                }

                // Helvetica does not need embedding — it is a standard PDF font
                $fontSize = max(6, min(24, (int) ($template->font_size ?? 10)));
                $pdf->SetFont('Helvetica', '', $fontSize);
                $pdf->SetTextColor(0, 0, 0);

                foreach ($fields as $field) {
                    $fieldName = $field['name'] ?? '';
                    $rawValue  = (string) ($values[$fieldName] ?? '');

                    if ($rawValue === '') {
                        continue;
                    }

                    // ── Convert coordinates from % of page to mm ──────────────
                    $mmX = ((float) ($field['x'] ?? 0)) / 100.0 * 210.0;
                    $mmY = ((float) ($field['y'] ?? 0)) / 100.0 * 297.0;
                    $mmW = ((float) ($field['w'] ?? 20)) / 100.0 * 210.0;


                    // ── Encode UTF-8 → windows-1252 for FPDF ──────────────────
                    // windows-1252 covers all Italian accented characters
                    // (à, è, é, ì, ò, ù) natively — no TRANSLIT needed.
                    $encoded = iconv('UTF-8', 'windows-1252//IGNORE', $rawValue);
                    if ($encoded === false || $encoded === '') {
                        $encoded = $rawValue; // fallback: use original, may show ? for non-Latin chars
                    }

                    $pdf->SetXY($mmX, $mmY);

                    if (($field['type'] ?? 'text') === 'textarea') {
                        // A capo automatico su più righe, dentro la larghezza mmW.
                        // L'altezza (h) è solo indicativa per l'editor: MultiCell non
                        // tronca il testo, continua a scrivere oltre se necessario.
                        $pdf->MultiCell($mmW, 5, $encoded, 0, 'L');
                    } else {
                        // ln=0 → cursor stays on same line after cell; border=0; align=L
                        $pdf->Cell($mmW, 5, $encoded, 0, 0, 'L');
                    }
                }
            }

            // ── 6. Write compiled PDF to temp file ────────────────────────────
            // Output('S') returns the PDF as a string without sending headers
            $pdfOutput = $pdf->Output('S');
            file_put_contents($outputTmp, $pdfOutput);

            if (! file_exists($outputTmp) || filesize($outputTmp) === 0) {
                throw new \RuntimeException('FPDI non ha prodotto un file PDF di output.');
            }

            // ── 7. Upload compiled PDF to S3 ──────────────────────────────────
            $s3Key = sprintf(
                'tenant_%d/pratica_%d/moduli/%s.pdf',
                $pratica->tenant_id,
                $pratica->id,
                (string) Str::uuid()
            );

            Storage::disk('s3')->put($s3Key, file_get_contents($outputTmp), 'private');

            // ── 8. Create Allegato record ─────────────────────────────────────
            Allegato::create([
                'pratica_id'           => $pratica->id,
                'tenant_id'            => $pratica->tenant_id,
                'nome_file'            => Str::slug($template->name) . '.pdf',
                's3_key'               => $s3Key,
                'document_category_id' => $template->output_document_category_id,
                'source'               => 'generato',
                'module_template_id'   => $template->id,
            ]);

            Log::info('PdfFormFillerService: PDF compilato con FPDI', [
                'pratica_module_id' => $praticaModule->id,
                'template'          => $template->name,
                'pages'             => $pageCount,
                's3_key'            => $s3Key,
            ]);

            return $s3Key;

        } catch (\RuntimeException $e) {
            Log::error('PdfFormFillerService: errore permanente', [
                'pratica_module_id' => $praticaModule->id,
                'template_id'       => $template->id ?? null,
                'errore'            => $e->getMessage(),
            ]);
            return '';

        } catch (\Throwable $e) {
            Log::error('PdfFormFillerService: errore transiente', [
                'pratica_module_id' => $praticaModule->id,
                'template_id'       => $template->id ?? null,
                'errore'            => $e->getMessage(),
                'trace'             => $e->getTraceAsString(),
            ]);
            throw $e;

        } finally {
            if ($inputTmp  && file_exists($inputTmp))  @unlink($inputTmp);
            if ($compatTmp && file_exists($compatTmp)) @unlink($compatTmp);
            if ($outputTmp && file_exists($outputTmp)) @unlink($outputTmp);
        }
    }

    /**
     * Use Ghostscript to rewrite the PDF as PDF 1.4 (uncompressed xrefs),
     * which the free FPDI parser can read. Returns the temp file path, or
     * null if Ghostscript is not available (FPDI will try the original file).
     */
    private function toCompatPdf(string $inputPath): ?string
    {
        $gs = collect(['/usr/bin/gs', '/usr/local/bin/gs', '/opt/homebrew/bin/gs'])
            ->first(fn ($b) => file_exists($b));

        if (! $gs) {
            exec('which gs 2>/dev/null', $whichOut, $whichCode);
            $gs = ($whichCode === 0 && ! empty($whichOut[0])) ? trim($whichOut[0]) : null;
        }

        if (! $gs) {
            Log::error('PdfFormFillerService: Ghostscript not found. Install with: sudo apt-get install ghostscript');
            return null;
        }

        $outputPath = $inputPath . '_compat.pdf';

        $cmd = sprintf(
            '%s -dBATCH -dNOPAUSE -dQUIET -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -sOutputFile=%s %s 2>&1',
            escapeshellarg($gs),
            escapeshellarg($outputPath),
            escapeshellarg($inputPath)
        );

        exec($cmd, $cmdOutput, $exitCode);

        if ($exitCode !== 0 || ! file_exists($outputPath) || filesize($outputPath) === 0) {
            Log::warning('PdfFormFillerService: Ghostscript conversion failed', [
                'exit_code' => $exitCode,
                'output'    => implode(' ', $cmdOutput),
            ]);
            return null;
        }

        return $outputPath;
    }

    /**
     * Generate a temporary signed URL for a compiled PDF (7 days).
     */
    public function temporaryDownloadUrl(string $s3Key): string
    {
        return Storage::disk('s3')->temporaryUrl($s3Key, now()->addDays(7));
    }
}
