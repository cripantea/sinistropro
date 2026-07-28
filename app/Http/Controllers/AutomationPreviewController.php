<?php

namespace App\Http\Controllers;

use App\Models\Automation;
use App\Models\Pratica;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutomationPreviewController extends Controller
{
    /**
     * Data l'azione che l'utente sta per compiere (cambio stato e/o cambio di
     * uno o più campi data osservati), restituisce l'elenco delle automazioni
     * "richiede conferma" che scatterebbero — usato dal frontend per mostrare
     * la modale di conferma PRIMA di salvare davvero.
     */
    public function preview(Request $request, Pratica $pratica): JsonResponse
    {
        $user = auth()->user();
        abort_unless($pratica->tenant_id === $user->tenant_id, 403);

        $data = $request->validate([
            'tenant_status_id' => ['nullable', 'integer'],
            'date_fields' => ['nullable', 'array'],
            'date_fields.*' => ['nullable', 'string'],
        ]);

        $matches = collect();

        if (! empty($data['tenant_status_id']) && (int) $data['tenant_status_id'] !== $pratica->current_status_id) {
            $matches = $matches->merge(
                Automation::where('tenant_id', $user->tenant_id)
                    ->where('trigger_type', 'status')
                    ->where('tenant_status_id', $data['tenant_status_id'])
                    ->where('is_active', true)
                    ->where('requires_confirmation', true)
                    ->get()
            );
        }

        foreach ($data['date_fields'] ?? [] as $field => $newValue) {
            $oldValue = $field === 'data_appuntamento'
                ? $pratica->ispezioni->first()?->data_appuntamento?->format('Y-m-d')
                : ($pratica->custom_fields[$field] ?? null);

            if ((string) ($oldValue ?? '') === (string) ($newValue ?? '')) {
                continue;
            }

            $matches = $matches->merge(
                Automation::where('tenant_id', $user->tenant_id)
                    ->where('trigger_type', 'date_field')
                    ->where('watched_field', $field)
                    ->where('is_active', true)
                    ->where('requires_confirmation', true)
                    ->get()
            );
        }

        return response()->json([
            'automations' => $matches->unique('id')->values()->map(fn (Automation $a) => [
                'id' => $a->id,
                'name' => $a->name,
            ]),
        ]);
    }
}
