<?php

namespace App\Http\Controllers;

use App\Events\PraticaCampoDataAggiornato;
use App\Events\PraticaStatoAggiornato;
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
     * Usato dalla board Kanban quando si sposta una card in una colonna 'external',
     * e dal pannello Sopralluogo/Perito nella pagina di dettaglio pratica.
     */
    public function store(Request $request, Pratica $pratica): JsonResponse
    {
        $user = auth()->user();

        // Verifica che l'utente abbia accesso a questa pratica (BelongsToTenant lo garantisce via route binding,
        // ma verifichiamo esplicitamente per il JSON path).
        abort_unless($pratica->tenant_id === $user->tenant_id, 403);

        $data = $request->validate([
            'current_status_id'    => ['nullable', 'integer', 'exists:tenant_statuses,id'],
            'assegnato_a_user_id'  => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $user->tenant_id)->where('role', 'external')],
            'carrozzeria_user_id'  => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $user->tenant_id)->where('role', 'external')],
            'data_appuntamento'    => ['nullable', 'date'],
            'note_sopralluogo'     => ['nullable', 'string', 'max:2000'],
        ]);

        $skip = $request->boolean('skip_confirmable_automations', false);

        $oldStatusId         = $pratica->current_status_id;
        $oldDataAppuntamento = (string) ($pratica->ispezioni->first()?->data_appuntamento?->format('Y-m-d') ?? '');
        $newDataAppuntamento = $request->has('data_appuntamento') ? (string) ($data['data_appuntamento'] ?? '') : null;

        DB::transaction(function () use ($pratica, $data, $request, $user): void {
            $update = [
                'assegnato_a_user_id' => $data['assegnato_a_user_id'] ?? null,
                'carrozzeria_user_id' => $data['carrozzeria_user_id'] ?? null,
                'stato'               => 'pianificata',
            ];

            // Aggiorna data e note solo se esplicitamente incluse nella richiesta
            if ($request->has('data_appuntamento')) {
                $update['data_appuntamento'] = $data['data_appuntamento'] ?? null;
            }
            if ($request->has('note_sopralluogo')) {
                $update['note_sopralluogo'] = $data['note_sopralluogo'] ?? null;
            }

            Ispezione::updateOrCreate(
                [
                    'tenant_id'  => $user->tenant_id,
                    'pratica_id' => $pratica->id,
                ],
                $update
            );

            // Aggiorna stato pratica solo se esplicitamente richiesto (Kanban)
            if (! empty($data['current_status_id'])) {
                $pratica->update(['current_status_id' => $data['current_status_id']]);
            }
        });

        // Event-driven: lancia il sistema Automazioni se lo stato è cambiato.
        $newStatusId = $data['current_status_id'] ?? null;
        if ($newStatusId && $newStatusId != $oldStatusId) {
            event(new PraticaStatoAggiornato($pratica, $oldStatusId, $newStatusId, $skip));
        }

        // Event-driven: lancia Automazioni se la data appuntamento è cambiata (path Kanban).
        if ($newDataAppuntamento !== null && $oldDataAppuntamento !== $newDataAppuntamento) {
            event(new PraticaCampoDataAggiornato($pratica, 'data_appuntamento', $skip));
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->back()->with('success', 'Sopralluogo salvato.');
    }
}
