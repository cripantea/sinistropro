<?php

namespace App\Http\Controllers\Impostazioni;

use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Support\AccessoImpostazioni;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Impostazioni → Campi: campi aggiuntivi dei sinistri e dei clienti. Il nome tecnico nasce
 * dall'etichetta e poi non cambia più (è la chiave con cui i valori sono salvati).
 */
class CampiController extends Controller
{
    public function show(): Response
    {
        $tenant = AccessoImpostazioni::tenant();

        return Inertia::render('Impostazioni/Campi', [
            'campi' => array_values($tenant->getCustomFieldsSchema()),
            'campiCliente' => array_values($tenant->getClienteCustomFieldsSchema()),
            'conClienti' => $tenant->hasFeature('clienti'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = AccessoImpostazioni::tenant();
        $regole = fn (string $k) => [
            $k => ['nullable', 'array', 'max:20'],
            "$k.*.name" => ['nullable', 'string', 'max:50'],
            "$k.*.label" => ['required', 'string', 'max:100'],
            "$k.*.type" => ['required', 'in:text,date,number,boolean,select'],
            "$k.*.required" => ['boolean'],
            "$k.*.options" => ['nullable', 'array', 'max:50'],
            "$k.*.options.*" => ['nullable', 'string', 'max:255'],
        ];
        $data = $request->validate([...$regole('campi'), ...$regole('campi_cliente')], [
            '*.*.label.required' => 'Ogni campo deve avere un nome.',
        ]);

        $campi = $this->normalizza($data['campi'] ?? [], $tenant->getCustomFieldsSchema(), 'campi');
        $campiCliente = $this->normalizza($data['campi_cliente'] ?? [], $tenant->getClienteCustomFieldsSchema(), 'campi_cliente');

        // Una data usata da un'automazione attiva non si può togliere: l'automazione resterebbe senza innesco.
        foreach ([['date_field', 'date_reminder'], ['cliente_date_field']] as $i => $tipi) {
            $nuovi = collect($i === 0 ? $campi : $campiCliente)->where('type', 'date')->pluck('name')->push('data_appuntamento');
            $usata = Automation::where('tenant_id', $tenant->id)->where('is_active', true)->whereIn('trigger_type', $tipi)
                ->whereNotIn('watched_field', $nuovi)->first();
            if ($usata) {
                throw ValidationException::withMessages([$i === 0 ? 'campi' : 'campi_cliente' => "La data usata dall'automazione \"{$usata->name}\" non si può togliere (o cambiare tipo): spegni prima l'automazione."]);
            }
        }

        $tenant->update(['settings' => [
            ...($tenant->settings ?? []),
            'custom_fields_schema' => $campi,
            'cliente_custom_fields_schema' => $campiCliente,
        ]]);

        return back()->with('success', 'Campi salvati.');
    }

    /** Nome tecnico: quello esistente se il campo c'era già, altrimenti ricavato dall'etichetta. */
    private function normalizza(array $campi, array $esistenti, string $chiave): array
    {
        $nomiEsistenti = collect($esistenti)->pluck('name')->all();
        $usati = [];
        $out = [];

        foreach (array_values($campi) as $i => $c) {
            $nome = in_array($c['name'] ?? null, $nomiEsistenti, true) ? $c['name'] : null;
            if (! $nome) {
                $base = Str::of($c['label'])->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->limit(40, '')->value() ?: 'campo';
                $base = preg_match('/^[a-z]/', $base) ? $base : 'campo_'.$base;
                $nome = $base;
                for ($n = 2; in_array($nome, [...$usati, ...$nomiEsistenti], true); $n++) {
                    $nome = $base.'_'.$n;
                }
            }
            if (in_array($nome, $usati, true)) {
                throw ValidationException::withMessages([$chiave => "Due campi hanno lo stesso nome tecnico ({$nome})."]);
            }
            $usati[] = $nome;

            $opzioni = array_values(array_filter(array_map(fn ($o) => trim((string) $o), $c['options'] ?? []), fn ($o) => $o !== ''));
            if ($c['type'] === 'select' && ! $opzioni) {
                throw ValidationException::withMessages(["{$chiave}.{$i}.options" => "Il campo \"{$c['label']}\" è una lista: aggiungi almeno un'opzione."]);
            }

            $out[] = [
                'name' => $nome,
                'label' => $c['label'],
                'type' => $c['type'],
                'required' => (bool) ($c['required'] ?? false),
                'options' => $c['type'] === 'select' ? $opzioni : [],
            ];
        }

        return $out;
    }
}
