<?php

namespace App\Http\Controllers;

use App\Models\Ispezione;
use App\Models\Pratica;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class IspezioneController extends Controller
{
    /**
     * Crea (o aggiorna) l'ispezione per una pratica e aggiorna lo stato della pratica.
     * Usato dalla board Kanban quando si sposta una card in una colonna 'external'.
     */
    public function store(Request $request, Pratica $pratica): JsonResponse
    {
        $user = auth()->user();

        // Verifica che l'utente abbia accesso a questa pratica (BelongsToTenant lo garantisce via route binding,
        // ma verifichiamo esplicitamente per il JSON path).
        abort_unless($pratica->tenant_id === $user->tenant_id, 403);

        $data = $request->validate([
            'current_status_id'    => ['required', 'integer', 'exists:tenant_statuses,id'],
            'assegnato_a_user_id'  => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $user->tenant_id)->where('role', 'external')],
            'data_appuntamento'    => ['nullable', 'date'],
        ]);

        DB::transaction(function () use ($pratica, $data, $user): void {
            // Crea o aggiorna l'ispezione attiva (stato != completata) per questa pratica
            Ispezione::updateOrCreate(
                [
                    'tenant_id'  => $user->tenant_id,
                    'pratica_id' => $pratica->id,
                ],
                [
                    'assegnato_a_user_id' => $data['assegnato_a_user_id'] ?? null,
                    'data_appuntamento'   => $data['data_appuntamento'] ?? null,
                    'stato'               => 'pianificata',
                ]
            );

            // Aggiorna lo stato della pratica
            $pratica->update(['current_status_id' => $data['current_status_id']]);
        });

        return response()->json(['ok' => true]);
    }
}
