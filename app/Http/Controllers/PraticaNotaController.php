<?php

namespace App\Http\Controllers;

use App\Http\Requests\PraticaNota\StorePraticaNotaRequest;
use App\Models\Pratica;
use App\Models\PraticaNota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class PraticaNotaController extends Controller
{
    /**
     * POST /pratiche/{pratica}/note
     *
     * Usato sia da Inertia (redirect) che da API (JSON).
     * La risposta viene differenziata in base all'Accept header.
     */
    public function store(
        StorePraticaNotaRequest $request,
        Pratica $pratica,
    ): JsonResponse|RedirectResponse {
        $nota = PraticaNota::create([
            'pratica_id' => $pratica->id,
            'tenant_id'  => $pratica->tenant_id,
            'user_id'    => auth()->id(),
            'nota'       => $request->validated('nota'),
        ]);

        $nota->load('user:id,name');

        if ($request->expectsJson()) {
            return response()->json([
                'data'    => $nota,
                'message' => 'Nota aggiunta con successo.',
            ], 201);
        }

        return redirect()
            ->route('pratiche.show', $pratica)
            ->with('success', 'Nota aggiunta.');
    }

    /**
     * DELETE /pratiche/{pratica}/note/{nota}
     *
     * Solo l'autore o un tenant-admin può eliminare la propria nota.
     */
    public function destroy(
        Pratica     $pratica,
        PraticaNota $nota,
    ): JsonResponse|RedirectResponse {
        $user = auth()->user();

        abort_unless(
            $nota->pratica_id === $pratica->id
            && ($nota->user_id === $user->id || $user->isTenantAdmin()),
            403,
            'Non puoi eliminare questa nota.'
        );

        $nota->delete();

        if (request()->expectsJson()) {
            return response()->json(['message' => 'Nota eliminata.']);
        }

        return redirect()
            ->route('pratiche.show', $pratica)
            ->with('success', 'Nota eliminata.');
    }
}
