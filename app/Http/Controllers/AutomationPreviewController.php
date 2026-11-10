<?php

namespace App\Http\Controllers;

use App\Models\Automation;
use App\Jobs\ExecuteAutomationJob;
use App\Models\AutomationRun;
use App\Models\Contatto;
use App\Models\Pratica;
use App\Models\TenantStatus;
use App\Services\AutomationPlanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            'tenant_status_id'        => ['nullable', 'integer', Rule::exists('tenant_statuses', 'id')->where('tenant_id', $pratica->tenant_id)],
            'date_fields'             => ['nullable', 'array'],
            'date_fields.*'           => ['nullable', 'string'],
            'perito_contatto_id'      => ['nullable', 'integer'],
            'carrozzeria_contatto_id' => ['nullable', 'integer'],
            'apertura'                => ['nullable', 'boolean'],
        ]);

        $matches = collect();

        // Sinistro appena aperto: automazioni "Nuovo sinistro aperto" ancora da confermare.
        if (! empty($data['apertura'])) {
            $matches = $matches->merge(
                Automation::where('tenant_id', $user->tenant_id)->where('trigger_type', 'pratica_created')->where('is_active', true)->get()
                    ->filter(fn (Automation $a) => ! AutomationRun::where(['automation_id' => $a->id, 'soggetto' => 'pratica', 'soggetto_id' => $pratica->id, 'chiave' => 'apertura'])->exists())
            );
        }

        if (! empty($data['tenant_status_id']) && (int) $data['tenant_status_id'] !== $pratica->current_status_id) {
            $matches = $matches->merge(
                Automation::where('tenant_id', $user->tenant_id)
                    ->perStato((int) $data['tenant_status_id'])
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

        $plans = $matches->unique('id')->values()
            ->map(fn (Automation $a) => $planner->planPratica($pratica, $a, $ctx));

        // Email "di cambio stato" impostata sullo stato stesso: anche lei si vede e si può
        // escludere nella conferma, come le automazioni (voce con id 0).
        if (! empty($data['tenant_status_id']) && (int) $data['tenant_status_id'] !== $pratica->current_status_id) {
            $stato = TenantStatus::where('tenant_id', $pratica->tenant_id)->find($data['tenant_status_id']);
            if ($stato?->send_email_notification) {
                $plans->prepend($planner->planEmailStato($pratica, $stato));
            }
        }

        return response()->json([
            // Rubrica attiva: nella finestra di conferma si possono aggiungere destinatari da qui.
            // Gli esterni non vedono la rubrica (stessa regola di ContattoController).
            'rubrica' => $user->role === 'external'
                ? []
                : Contatto::where('is_active', true)->orderBy('nome')->get(['id', 'nome', 'tags', 'telefono', 'email']),
            'automations' => $plans->values(),
        ]);
    }

    /**
     * Conferma delle automazioni "Nuovo sinistro aperto" dalla finestra mostrata dopo l'apertura:
     * partono solo quelle confermate, ai destinatari scelti, e una volta sola per sinistro.
     */
    public function apertura(Request $request, Pratica $pratica): JsonResponse
    {
        abort_unless($pratica->tenant_id === $request->user()->tenant_id, 403);
        $overrides = AutomationPlanner::normalizeOverrides($request->input('automation_overrides'));
        $nessuna = $request->boolean('skip');
        $inviate = [];

        foreach (Automation::where('tenant_id', $pratica->tenant_id)->where('trigger_type', 'pratica_created')->where('is_active', true)->get() as $automation) {
            $override = $overrides[$automation->id] ?? null;
            // Registrata anche se non inviata: la finestra non si ripresenta per questo sinistro.
            if (! AutomationRun::prima($automation, 'pratica', $pratica->id, 'apertura') || $nessuna || ($override && ! $override['send'])) {
                continue;
            }
            ExecuteAutomationJob::dispatch($pratica, $automation, $override ? ['recipients' => $override['recipients'], 'cc' => $override['cc']] : null);
            $inviate[] = $automation->id;
        }

        return response()->json(['inviate' => $inviate]);
    }
}
