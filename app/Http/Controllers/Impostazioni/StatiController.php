<?php

namespace App\Http\Controllers\Impostazioni;

use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\Pratica;
use App\Models\Tenant;
use App\Support\AccessoImpostazioni;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Impostazioni → Stati e avvisi: stati dei sinistri, colori, chiusi, iniziale e giorni di avviso per stato. */
class StatiController extends Controller
{
    public function show(): Response
    {
        $tenant = AccessoImpostazioni::tenant();
        $conteggi = Pratica::acrossAllTenants()->where('tenant_id', $tenant->id)
            ->selectRaw('current_status_id, count(*) as n')->groupBy('current_status_id')->pluck('n', 'current_status_id');

        return Inertia::render('Impostazioni/Stati', [
            'stati' => $tenant->statuses()->orderBy('order')->get()->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'color' => $s->color,
                'is_closed' => (bool) $s->is_closed,
                'is_initial' => (bool) $s->is_initial,
                'notice_days' => $s->notice_days,
                'sinistri' => (int) ($conteggi[$s->id] ?? 0),
                'automazioni' => $s->automations()->count(),
            ]),
            'giorniPredefiniti' => $tenant->getDefaultNoticeDays(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = AccessoImpostazioni::tenant();
        $data = $request->validate([
            'stati' => ['required', 'array', 'min:1', 'max:20'],
            'stati.*.id' => ['nullable', 'integer'],
            'stati.*.name' => ['required', 'string', 'max:100'],
            'stati.*.color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'stati.*.is_closed' => ['boolean'],
            'stati.*.is_initial' => ['boolean'],
            'stati.*.notice_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ], [
            'stati.*.name.required' => 'Ogni stato deve avere un nome.',
            'stati.*.color.regex' => 'Colore non valido.',
            'stati.*.notice_days.min' => 'I giorni di avviso vanno da 1 a 365 (vuoto = predefiniti).',
        ]);

        if (! collect($data['stati'])->contains(fn ($s) => ! empty($s['is_initial']))) {
            throw ValidationException::withMessages(['stati' => 'Scegli lo stato iniziale (quello dei sinistri appena aperti).']);
        }

        self::sincronizza($tenant, $data['stati']);

        return back()->with('success', 'Stati salvati.');
    }

    /**
     * Salva gli stati. Non si può eliminare uno stato con sinistri dentro; le automazioni legate a uno
     * stato eliminato vengono spente (altrimenti, con lo stato vuoto, scatterebbero a ogni cambio di stato).
     */
    public static function sincronizza(Tenant $tenant, array $stati): void
    {
        DB::transaction(function () use ($tenant, $stati): void {
            $tenuti = collect($stati)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
            $daEliminare = $tenant->statuses()->whereNotIn('id', $tenuti)->get();

            foreach ($daEliminare as $stato) {
                $n = Pratica::acrossAllTenants()->where('current_status_id', $stato->id)->count();
                if ($n > 0) {
                    throw ValidationException::withMessages(['stati' => "Lo stato \"{$stato->name}\" ha {$n} sinistri: spostali in un altro stato prima di eliminarlo."]);
                }
                Automation::where('tenant_status_id', $stato->id)->update(['is_active' => false]);
                $stato->delete();
            }

            $iniziale = false;
            foreach (array_values($stati) as $i => $s) {
                $isIniziale = ! $iniziale && ! empty($s['is_initial']);
                $iniziale = $iniziale || $isIniziale;
                $esistente = ! empty($s['id']) ? $tenant->statuses()->find($s['id']) : null;

                $valori = [
                    'name' => $s['name'],
                    'color' => $s['color'],
                    'is_closed' => (bool) ($s['is_closed'] ?? false),
                    'is_initial' => $isIniziale,
                    'order' => $i,
                ];
                if (array_key_exists('notice_days', $s)) {
                    $valori['notice_days'] = $s['notice_days'] ?: null;
                }

                $esistente ? $esistente->update($valori) : $tenant->statuses()->create($valori);
            }
        });
    }
}
