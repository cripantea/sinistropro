<?php

namespace App\Http\Controllers;

use App\Http\Requests\Allegato\StoreAllegatoRequest;
use App\Models\Allegato;
use App\Models\Pratica;
use App\Services\AllegatoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class AllegatoController extends Controller
{
    public function __construct(private readonly AllegatoService $allegatoService)
    {
    }

    public function store(StoreAllegatoRequest $request, Pratica $pratica): JsonResponse
    {
        $categoryId = $request->integer('document_category_id') ?: null;
        $tenantId   = auth()->user()->tenant_id;

        if ($categoryId !== null) {
            $pivot = DB::table('tenant_document_categories')
                ->where('tenant_id', $tenantId)
                ->where('document_category_id', $categoryId)
                ->first();

            $isEnabled  = $pivot ? (bool) $pivot->is_enabled  : true;
            $maxSizeMb  = $pivot ? (int)  $pivot->max_file_size_mb : 50;

            if (! $isEnabled) {
                return response()->json(['message' => 'Categoria non disponibile per questo tenant.'], 422);
            }

            $fileSizeMb = $request->file('file')->getSize() / (1024 * 1024);
            if ($fileSizeMb > $maxSizeMb) {
                return response()->json([
                    'message' => "Il file supera il limite di {$maxSizeMb} MB per questa categoria.",
                ], 422);
            }
        }

        $allegato = $this->allegatoService->upload($request->file('file'), $pratica, $categoryId);
        $allegato->load('category:id,name');

        return response()->json(['allegato' => $allegato], 201);
    }

    public function download(Allegato $allegato): JsonResponse
    {
        $url = $this->allegatoService->generatePresignedUrl($allegato);

        return response()->json(['url' => $url]);
    }

    public function destroy(Allegato $allegato): JsonResponse|RedirectResponse
    {
        $this->allegatoService->delete($allegato);

        if (request()->expectsJson()) {
            return response()->json(['message' => 'Eliminato.']);
        }

        return redirect()->back()->with('success', 'Allegato eliminato.');
    }
}
