<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ValidaJson;
use App\Models\Cliente;
use App\Models\Veicolo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/** Veicoli di un cliente: dalla scheda cliente e dal nuovo sinistro (chiamate axios). */
class VeicoloController extends Controller
{
    use ValidaJson;

    public const CAMPI = ['id', 'targa', 'telaio', 'marca', 'modello', 'descrizione', 'data_immatricolazione', 'scadenza_revisione', 'km'];

    public function store(Request $request, Cliente $cliente): JsonResponse
    {
        $veicolo = $cliente->veicoli()->create($this->dati($request) + ['tenant_id' => $cliente->tenant_id]);

        return response()->json(['veicolo' => Arr::only($veicolo->fresh()->toArray(), self::CAMPI)], 201);
    }

    public function update(Request $request, Veicolo $veicolo): JsonResponse
    {
        $veicolo->update($this->dati($request, $veicolo));

        return response()->json(['veicolo' => Arr::only($veicolo->fresh()->toArray(), self::CAMPI)]);
    }

    public function destroy(Veicolo $veicolo): JsonResponse
    {
        // Un veicolo collegato a un sinistro non si cancella: si perderebbe il dato sul sinistro.
        if ($veicolo->pratiche()->exists()) {
            return response()->json(['message' => 'Il veicolo è collegato a dei sinistri: non si può eliminare.'], 422);
        }

        $veicolo->delete();

        return response()->json(['ok' => true]);
    }

    private function dati(Request $request, ?Veicolo $veicolo = null): array
    {
        // Targa e telaio senza spazi e in maiuscolo, come arrivano dall'import.
        $request->merge(collect(['targa', 'telaio'])->mapWithKeys(fn ($k) => [
            $k => ($v = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $request->input($k)))) !== '' ? $v : null,
        ])->all());

        return $this->validaJson($request, [
            'targa'                 => ['required_without:telaio', 'nullable', 'string', 'max:20'],
            'telaio'                => ['nullable', 'string', 'max:30',
                Rule::unique('veicoli', 'telaio')->where('tenant_id', $veicolo?->tenant_id ?? $request->route('cliente')?->tenant_id)->ignore($veicolo?->id)],
            'marca'                 => ['nullable', 'string', 'max:50'],
            'modello'               => ['nullable', 'string', 'max:100'],
            'descrizione'           => ['nullable', 'string', 'max:255'],
            'data_immatricolazione' => ['nullable', 'date'],
            'scadenza_revisione'    => ['nullable', 'date'],
            'km'                    => ['nullable', 'integer', 'min:0', 'max:4000000000'],
        ], [
            'targa.required_without' => 'Inserisci la targa (o il telaio).',
            'telaio.unique'          => 'Esiste già un veicolo con questo telaio.',
        ]);
    }
}
