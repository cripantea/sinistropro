<?php

namespace App\Http\Controllers;

use App\Models\Allegato;
use App\Models\ModuleTemplate;
use App\Models\Pratica;
use App\Models\PraticaModule;
use App\Services\PdfFormFillerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PraticaModuleController extends Controller
{
    public function __construct(private readonly PdfFormFillerService $pdfService) {}

    /**
     * Salva/aggiorna i valori del modulo. Se generate_pdf è true (default)
     * compila anche il PDF su S3, altrimenti salva solo la bozza — utile per
     * campi parziali che si vogliono completare più avanti.
     * Restituisce il nuovo Allegato per aggiornamento ottimistico del frontend.
     */
    public function store(Request $request, Pratica $pratica): JsonResponse
    {
        $user = auth()->user();
        abort_unless($pratica->tenant_id === $user->tenant_id, 403);

        $validated = $request->validate([
            'module_template_id' => ['required', 'integer'],
            'values'             => ['required', 'array'],
            'generate_pdf'       => ['sometimes', 'boolean'],
        ]);

        // Verifica che il template appartenga al tenant corrente
        $template = ModuleTemplate::where('id', $validated['module_template_id'])
            ->where('tenant_id', $user->tenant_id)
            ->firstOrFail();

        // Filtra i values tenendo solo le chiavi definite nello schema
        $allowedKeys    = array_column($template->fields_schema ?? [], 'name');
        $filteredValues = array_intersect_key(
            $validated['values'],
            array_flip($allowedKeys)
        );

        // Upsert: un solo record per coppia pratica+template
        $module = PraticaModule::updateOrCreate(
            [
                'pratica_id'         => $pratica->id,
                'module_template_id' => $template->id,
            ],
            ['values' => $filteredValues]
        );

        if (! $request->boolean('generate_pdf', true)) {
            return response()->json([
                'module'   => $module,
                'allegato' => null,
                'warning'  => null,
            ]);
        }

        // Compila il PDF e crea l'Allegato su S3 (sincrono)
        $s3Key   = $this->pdfService->compile($module);
        $allegato = $s3Key
            ? Allegato::where('s3_key', $s3Key)->with('category:id,name')->first()
            : null;

        return response()->json([
            'module'   => $module,
            'allegato' => $allegato,
            'warning'  => $s3Key ? null : 'PDF non generato: verifica che il template abbia un PDF matrice configurato e che il file S3 sia raggiungibile. Controlla i log per i dettagli.',
        ], 201);
    }
}
