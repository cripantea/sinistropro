<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\ModuleTemplate;
use App\Models\Tenant;
use App\Services\AiFieldExtractorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ModuleTemplateController extends Controller
{
    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'name'                        => ['required', 'string', 'max:255'],
            'output_document_category_id' => [
                'nullable',
                'integer',
                'exists:document_categories,id',
                Rule::unique('module_templates', 'output_document_category_id')->where('tenant_id', $tenant->id),
            ],
            'pdf_template_s3_key'         => ['nullable', 'string', 'max:1000'],
            'fields_schema'               => ['nullable', 'array'],
            'fields_schema.*.name'     => ['required', 'string', 'max:100'],
            'fields_schema.*.label'    => ['required', 'string', 'max:255'],
            'fields_schema.*.type'     => ['required', Rule::in(['text', 'date', 'number', 'boolean', 'select'])],
            'fields_schema.*.required' => ['boolean'],
            'fields_schema.*.page'     => ['nullable', 'integer', 'min:1'],
            'fields_schema.*.x'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fields_schema.*.y'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fields_schema.*.w'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'font_size'                => ['nullable', 'integer', 'min:6', 'max:24'],
        ], [
            'output_document_category_id.unique' => 'Questa categoria ha già un modulo associato. Ogni categoria può avere al massimo un modulo.',
        ]);

        ModuleTemplate::create([
            'tenant_id'                   => $tenant->id,
            'name'                        => $data['name'],
            'output_document_category_id' => $data['output_document_category_id'] ?? null,
            'pdf_template_s3_key'         => $data['pdf_template_s3_key'] ?? null,
            'fields_schema'               => $data['fields_schema'] ?? [],
            'font_size'                   => $data['font_size'] ?? 10,
        ]);

        return redirect()
            ->to(route('superadmin.tenants.edit', $tenant) . '?tab=modules')
            ->with('success', "Template \"{$data['name']}\" creato.");
    }

    public function update(Request $request, Tenant $tenant, ModuleTemplate $moduleTemplate): RedirectResponse
    {
        abort_unless($moduleTemplate->tenant_id === $tenant->id, 403);

        $data = $request->validate([
            'name'                        => ['required', 'string', 'max:255'],
            'output_document_category_id' => [
                'nullable',
                'integer',
                'exists:document_categories,id',
                Rule::unique('module_templates', 'output_document_category_id')
                    ->where('tenant_id', $tenant->id)
                    ->ignore($moduleTemplate->id),
            ],
            'pdf_template_s3_key'         => ['nullable', 'string', 'max:1000'],
            'fields_schema'               => ['nullable', 'array'],
            'fields_schema.*.name'     => ['required', 'string', 'max:100'],
            'fields_schema.*.label'    => ['required', 'string', 'max:255'],
            'fields_schema.*.type'     => ['required', Rule::in(['text', 'date', 'number', 'boolean', 'select'])],
            'fields_schema.*.required' => ['boolean'],
            'fields_schema.*.page'     => ['nullable', 'integer', 'min:1'],
            'fields_schema.*.x'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fields_schema.*.y'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fields_schema.*.w'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'font_size'                => ['nullable', 'integer', 'min:6', 'max:24'],
        ], [
            'output_document_category_id.unique' => 'Questa categoria ha già un modulo associato. Ogni categoria può avere al massimo un modulo.',
        ]);

        $moduleTemplate->update([
            'name'                        => $data['name'],
            'output_document_category_id' => $data['output_document_category_id'] ?? null,
            'pdf_template_s3_key'         => $data['pdf_template_s3_key'] ?? $moduleTemplate->pdf_template_s3_key,
            'fields_schema'               => $data['fields_schema'] ?? [],
            'font_size'                   => $data['font_size'] ?? $moduleTemplate->font_size,
        ]);

        return redirect()
            ->to(route('superadmin.tenants.edit', $tenant) . '?tab=modules')
            ->with('success', "Template \"{$moduleTemplate->name}\" aggiornato.");
    }

    public function destroy(Tenant $tenant, ModuleTemplate $moduleTemplate): RedirectResponse
    {
        abort_unless($moduleTemplate->tenant_id === $tenant->id, 403);

        $name = $moduleTemplate->name;

        if ($moduleTemplate->pdf_template_s3_key) {
            try {
                Storage::disk('s3')->delete($moduleTemplate->pdf_template_s3_key);
            } catch (\Throwable $e) {
                Log::warning('ModuleTemplateController: S3 delete failed', [
                    'key'   => $moduleTemplate->pdf_template_s3_key,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $moduleTemplate->delete();

        return redirect()
            ->to(route('superadmin.tenants.edit', $tenant) . '?tab=modules')
            ->with('success', "Template \"{$name}\" eliminato.");
    }

    /**
     * Convert one page of a PDF stored on S3 to a JPEG preview using Ghostscript.
     * Returns { image: "data:image/jpeg;base64,...", page: int }.
     */
    public function previewPage(Request $request): JsonResponse
    {
        $request->validate([
            's3_key' => ['required', 'string', 'max:1000'],
            'page'   => ['integer', 'min:1'],
        ]);

        $s3Key = $request->input('s3_key');
        $page  = max(1, (int) $request->input('page', 1));

        try {
            $pdfContent = Storage::disk('s3')->get($s3Key);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'PDF non trovato su S3.'], 404);
        }

        if (! $pdfContent) {
            return response()->json(['error' => 'PDF non trovato su S3.'], 404);
        }

        $tmpPdf  = tempnam(sys_get_temp_dir(), 'prev_pdf_');
        $tmpJpeg = $tmpPdf . '.jpg';

        try {
            file_put_contents($tmpPdf, $pdfContent);

            // Resolve gs binary — check common paths then fall back to PATH
            $gsBin = collect(['/usr/bin/gs', '/usr/local/bin/gs', '/opt/homebrew/bin/gs'])
                ->first(fn ($b) => file_exists($b));

            if (! $gsBin) {
                // Last resort: hope gs is in PATH
                exec('which gs 2>/dev/null', $whichOut, $whichCode);
                $gsBin = ($whichCode === 0 && ! empty($whichOut[0])) ? trim($whichOut[0]) : null;
            }

            if (! $gsBin) {
                Log::error('ModuleTemplateController: Ghostscript not found on server. Install with: sudo apt-get install ghostscript');
                return response()->json(['error' => 'Ghostscript non installato sul server (sudo apt-get install ghostscript).'], 422);
            }

            $cmd = sprintf(
                '%s -dBATCH -dNOPAUSE -dQUIET -sDEVICE=jpeg -dJPEGQ=88 -r150 -dFirstPage=%d -dLastPage=%d -sOutputFile=%s %s 2>&1',
                escapeshellarg($gsBin),
                $page,
                $page,
                escapeshellarg($tmpJpeg),
                escapeshellarg($tmpPdf)
            );

            exec($cmd, $cmdOut, $exitCode);

            if ($exitCode !== 0 || ! file_exists($tmpJpeg) || filesize($tmpJpeg) === 0) {
                Log::warning('ModuleTemplateController: preview generation failed', [
                    'gs'   => $gsBin,
                    'exit' => $exitCode,
                    'out'  => implode(' ', $cmdOut),
                ]);
                return response()->json(['error' => 'Impossibile generare l\'anteprima PDF.'], 422);
            }

            return response()->json([
                'image' => 'data:image/jpeg;base64,' . base64_encode(file_get_contents($tmpJpeg)),
                'page'  => $page,
            ]);
        } finally {
            if (file_exists($tmpPdf))  @unlink($tmpPdf);
            if (file_exists($tmpJpeg)) @unlink($tmpJpeg);
        }
    }

    public function extractFields(Request $request, Tenant $tenant): JsonResponse
    {
        $request->validate([
            'pdf_file' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ]);

        $s3Key = "pdf_templates/tenant_{$tenant->id}/" . Str::uuid() . '.pdf';

        try {
            $file = $request->file('pdf_file');
            Storage::disk('s3')->put($s3Key, $file->get(), 'private');
        } catch (\Throwable $e) {
            Log::warning('ModuleTemplateController: S3 upload failed in extractFields', [
                'error' => $e->getMessage(),
            ]);
            // Keep the generated key even if upload failed — mock mode still works
        }

        try {
            $fields = app(AiFieldExtractorService::class)->extractFromPdf($s3Key);
        } catch (\Throwable $e) {
            Log::error('ModuleTemplateController: extractFields failed', [
                'error'  => $e->getMessage(),
                's3_key' => $s3Key,
            ]);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            's3_key' => $s3Key,
            'fields' => $fields,
        ]);
    }
}
