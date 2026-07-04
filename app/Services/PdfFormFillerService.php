<?php

namespace App\Services;

use App\Models\Allegato;
use App\Models\PraticaModule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use mikehaertl\pdftk\Pdf;

class PdfFormFillerService
{
    /**
     * Compila il PDF matrice AcroForm con i valori del modulo,
     * lo appiattisce, lo carica su S3 e crea il record Allegato.
     *
     * @return string  S3 key del PDF compilato, o stringa vuota in caso di errore
     */
    public function compile(PraticaModule $praticaModule): string
    {
        $praticaModule->loadMissing(['template', 'pratica']);

        $template = $praticaModule->template;
        $pratica  = $praticaModule->pratica;

        $inputTmp  = null;
        $outputTmp = null;

        try {
            // ── Validazione pre-esecuzione ─────────────────────────────────
            if (! $template->pdf_template_s3_key) {
                throw new \RuntimeException(
                    "ModuleTemplate #{$template->id} ({$template->name}) non ha un PDF matrice configurato."
                );
            }

            // ── 1. Scarica il PDF matrice da S3 in un file temporaneo ──────
            $pdfContent = Storage::disk('s3')->get($template->pdf_template_s3_key);

            if ($pdfContent === false || $pdfContent === null) {
                throw new \RuntimeException(
                    "PDF matrice non trovato su S3: {$template->pdf_template_s3_key}"
                );
            }

            $inputTmp  = tempnam(sys_get_temp_dir(), 'pdftk_in_');
            $outputTmp = $inputTmp . '_out.pdf';

            file_put_contents($inputTmp, $pdfContent);

            // ── 2. Riempie i campi AcroForm e appiattisce ─────────────────
            $pdf = new Pdf($inputTmp);
            $pdf->fillForm($praticaModule->values)
                ->flatten()
                ->saveAs($outputTmp);

            // php-pdftk non lancia eccezioni: l'errore è in getError()
            $pdftkError = $pdf->getError();
            if ($pdftkError) {
                throw new \RuntimeException("pdftk error: {$pdftkError}");
            }

            if (! file_exists($outputTmp) || filesize($outputTmp) === 0) {
                throw new \RuntimeException(
                    'pdftk non ha prodotto output: il binario pdftk è installato sul server?'
                );
            }

            // ── 3. Carica il PDF compilato su S3 ──────────────────────────
            $s3Key = sprintf(
                'tenant_%d/pratica_%d/moduli/%s.pdf',
                $pratica->tenant_id,
                $pratica->id,
                (string) Str::uuid()
            );

            Storage::disk('s3')->put($s3Key, file_get_contents($outputTmp), 'private');

            // ── 4. Crea il record Allegato ────────────────────────────────
            // tenant_id passato esplicitamente → BelongsToTenant::creating() non sovrascrive
            // (auth() non è attivo quando chiamato da un Job)
            Allegato::create([
                'pratica_id'           => $pratica->id,
                'tenant_id'            => $pratica->tenant_id,
                'nome_file'            => Str::slug($template->name) . '.pdf',
                's3_key'               => $s3Key,
                'document_category_id' => $template->output_document_category_id,
            ]);

            Log::info('PdfFormFillerService: PDF compilato', [
                'pratica_module_id' => $praticaModule->id,
                'template'          => $template->name,
                's3_key'            => $s3Key,
            ]);

            return $s3Key;

        } catch (\RuntimeException $e) {
            // Errori di configurazione o di pdftk: non recuperabili con retry
            Log::error('PdfFormFillerService: errore permanente', [
                'pratica_module_id' => $praticaModule->id,
                'template_id'       => $template->id,
                'errore'            => $e->getMessage(),
            ]);
            return '';

        } catch (\Throwable $e) {
            // Errori transienti (S3, filesystem): il caller (Job) può fare retry
            Log::error('PdfFormFillerService: errore transiente', [
                'pratica_module_id' => $praticaModule->id,
                'template_id'       => $template->id,
                'errore'            => $e->getMessage(),
                'trace'             => $e->getTraceAsString(),
            ]);
            // Rilancio per permettere il retry del Job upstream
            throw $e;

        } finally {
            // Pulizia sempre garantita anche in caso di eccezione
            if ($inputTmp  && file_exists($inputTmp))  @unlink($inputTmp);
            if ($outputTmp && file_exists($outputTmp)) @unlink($outputTmp);
        }
    }

    /**
     * Genera un URL temporaneo S3 (7 giorni) per il PDF compilato.
     * Utility per mostrare un link di download dopo la compilazione.
     */
    public function temporaryDownloadUrl(string $s3Key): string
    {
        return Storage::disk('s3')->temporaryUrl($s3Key, now()->addDays(7));
    }
}
