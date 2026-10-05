<?php

namespace App\Http\Controllers;

use App\Models\Automation;
use App\Models\Pratica;
use App\Services\AutomationPlanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutomationPreviewController extends Controller
{
    /**
     * Data l'azione che l'utente sta per compiere (cambio stato e/o cambio di uno o più
     * campi data osservati), restituisce TUTTE le automazioni attive che scatterebbero,
     * ciascuna con messaggio compilato e destinatari: il frontend le mostra in una finestra
     * di conferma PRIMA di salvare, dove l'utente può togliere/aggiungere destinatari.
     */
    public function preview(Request $request, Pratica $pratica, AutomationPlanner $planner): JsonResponse
    {
        $user = auth()->user();
        abort_unless($pratica->tenant_id === $user->tenant_id, 403);

        $data = $request->validate([
            'tenant_status_id'        => ['nullable', 'integer'],
            'date_fields'             => ['nullable', 'array'],
            'date_fields.*'           => ['nullable', 'string'],
            'perito_contatto_id'      => ['nullable', 'integer'],
            'carrozzeria_contatto_id' => ['nullable', 'integer'],
        ]);

        $matches = collect();

        if (! empty($data['tenant_status_id']) && (int) $data['tenant_status_id'] !== $pratica->current_status_id) {
            $matches = $matches->merge(
                Automation::where('tenant_id', $user->tenant_id)
                    ->where('trigger_type', 'status')
                    ->where('tenant_status_id', $data['tenant_status_id'])
                    ->where('is_active', true)
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
                    ->get()
            );
        }

        // Valori non ancora salvati: lo stato di destinazione e perito/carrozzeria scelti nella modale.
        $ctx = [
            'status_id'               => $data['tenant_status_id'] ?? null,
            'perito_contatto_id'      => $data['perito_contatto_id'] ?? null,
            'carrozzeria_contatto_id' => $data['carrozzeria_contatto_id'] ?? null,
        ];

        return response()->json([
            'automations' => $matches->unique('id')->values()
                ->map(fn (Automation $a) => $planner->planPratica($pratica, $a, $ctx)),
        ]);
    }
}
