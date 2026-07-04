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
     * Salva/aggiorna i valori del modulo e genera il PDF compilato su S3.
     * Restituisce il nuovo Allegato per aggiornamento ottimistico del frontend.
     */
    public function store(Request $request, Pratica $pratica): JsonResponse
    {
        $user = auth()->user();
        abort_unless($pratica->tenant_id === $user->tenant_id, 403);

        $validated = $request->validate([
            'module_template_id' => ['required', 'integer'],
            'values'             => ['required', 'array'],
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

        // Compila il PDF e crea l'Allegato su S3 (sincrono)
        $s3Key   = $this->pdfService->compile($module);
        $allegato = $s3Key
            ? Allegato::where('s3_key', $s3Key)->with('category:id,name')->first()
            : null;

        return response()->json([
            'module'   => $module,
            'allegato' => $allegato,
            'warning'  => $s3Key ? null : 'PDF non generato: verifica che il template abbia un PDF matrice e che pdftk sia installato sul server.',
        ], 201);
    }
}
