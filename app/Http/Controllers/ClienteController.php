<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ValidaJson;
use App\Http\Requests\Cliente\StoreClienteRequest;
use App\Http\Requests\Cliente\UpdateClienteRequest;
use App\Models\Cliente;
use App\Models\Veicolo;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClienteController extends Controller
{
    use ValidaJson;

    public function index(Request $request): Response
    {
        $search = $request->input('search', '');
        $schema = TenantContext::tenant()?->getClienteCustomFieldsSchema() ?? [];

        $clienti = Cliente::withCount('pratiche')
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                  ->orWhere('telefono', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('codice_esterno', $search)
                  ->orWhere('codice_fiscale', $search)
                  ->orWhere('partita_iva', $search)
                  ->orWhereHas('veicoli', fn ($v) => $v->where('targa', strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $search))));
            }))
            ->orderBy('nome')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Clienti/Index', [
            'clienti' => $clienti,
            'schema'  => $schema,
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Autocomplete del nuovo sinistro: cerca mentre si digita, sul server, così anche con
     * decine di migliaia di clienti la pagina non deve caricarli tutti.
     * Per nome, telefono, email, codice gestionale, codice fiscale, P.IVA o targa.
     */
    public function cerca(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['risultati' => []]);
        }

        $like = '%' . $q . '%';
        $parole = array_slice(array_values(array_filter(preg_split('/\s+/', $q), fn ($p) => mb_strlen($p) >= 2)), 0, 5) ?: [$q];
        $digits = preg_replace('/\D/', '', $q);
        $targa = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $q));

        // Le targhe si cercano prima, direttamente sui veicoli (indice su targa).
        $perTarga = strlen($targa) >= 3
            ? Veicolo::where('targa', 'like', "{$targa}%")->orderBy('targa')->limit(50)->get(['id', 'cliente_id', 'targa', 'marca', 'modello'])
            : collect();

        $clienti = Cliente::query()
            ->where(function ($w) use ($q, $like, $digits, $perTarga, $parole) {
                // "mario rossi" trova anche "ROSSI MARIO": ogni parola deve comparire nel nome, in qualsiasi ordine.
                $w->where(function ($n) use ($parole) {
                    foreach ($parole as $p) {
                        $n->where('nome', 'like', "%{$p}%");
                    }
                })
                  ->orWhere('email', 'like', $like)
                  ->orWhere('codice_esterno', $q)
                  ->orWhere('codice_fiscale', strtoupper($q))
                  ->orWhere('partita_iva', $q);
                if (strlen($digits) >= 4) {
                    // Telefoni scritti a mano con spazi, punti o trattini: si confrontano solo le cifre.
                    $w->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(telefono, ' ', ''), '.', ''), '-', ''), '/', '') like ?", ["%{$digits}%"]);
                }
                if ($perTarga->isNotEmpty()) {
                    $w->orWhereIn('id', $perTarga->pluck('cliente_id')->unique());
                }
            })
            ->orderBy('nome')
            ->limit(15)
            ->get(['id', 'nome', 'telefono', 'email', 'citta', 'codice_esterno', 'codice_fiscale']);

        $veicoliCount = Veicolo::whereIn('cliente_id', $clienti->pluck('id'))
            ->selectRaw('cliente_id, count(*) as n')->groupBy('cliente_id')->pluck('n', 'cliente_id');
        $veicoloTrovato = $perTarga->groupBy('cliente_id')->map->first();

        return response()->json([
            'risultati' => $clienti->map(fn (Cliente $c) => [
                'id'            => $c->id,
                'nome'          => $c->nome,
                'dettaglio'     => collect([$c->telefono, $c->citta, $c->codice_esterno ? "cod. {$c->codice_esterno}" : null])->filter()->implode(' · '),
                'veicoli_count' => (int) ($veicoliCount[$c->id] ?? 0),
                // Cercando una targa (es. un'auto di una flotta) si propone direttamente quel veicolo.
                'veicolo'       => $veicoloTrovato->get($c->id)?->only(['id', 'targa', 'marca', 'modello']),
            ]),
        ]);
    }

    /** Scheda completa del cliente per il nuovo sinistro: anagrafica e veicoli. */
    public function scheda(Cliente $cliente): JsonResponse
    {
        return response()->json([
            'cliente' => $cliente->only(['id', 'nome', 'telefono', 'email', 'codice_esterno', 'codice_fiscale', 'partita_iva', 'indirizzo', 'cap', 'citta', 'provincia']),
            'veicoli' => $cliente->veicoli()->orderByRaw('targa is null')->orderBy('targa')
                ->get(VeicoloController::CAMPI),
        ]);
    }

    /** Completa i dati del cliente dalla scheda del nuovo sinistro: si scrivono solo i campi inviati. */
    public function aggiornaDati(Request $request, Cliente $cliente): JsonResponse
    {
        $data = $this->validaJson($request, [
            'nome'           => ['sometimes', 'required', 'string', 'max:255'],
            'telefono'       => ['sometimes', 'nullable', 'string', 'max:30'],
            'email'          => ['sometimes', 'nullable', 'email', 'max:255'],
            'codice_fiscale' => ['sometimes', 'nullable', 'string', 'max:16'],
            'partita_iva'    => ['sometimes', 'nullable', 'string', 'max:20'],
            'indirizzo'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'cap'            => ['sometimes', 'nullable', 'string', 'max:10'],
            'citta'          => ['sometimes', 'nullable', 'string', 'max:100'],
            'provincia'      => ['sometimes', 'nullable', 'string', 'max:5'],
        ], ['email.email' => 'Inserisci un indirizzo email valido.']);

        foreach (['codice_fiscale', 'provincia'] as $k) {
            if (isset($data[$k])) {
                $data[$k] = strtoupper(trim($data[$k]));
            }
        }

        $cliente->update($data);

        return response()->json(['cliente' => $cliente->only(array_merge(['id'], array_keys($data)))]);
    }

    public function show(Cliente $cliente): Response
    {
        $cliente->loadCount('pratiche');
        $cliente->load([
            'pratiche' => fn ($q) => $q->with('currentStatus:id,name,color')->latest()->limit(10),
            'veicoli'  => fn ($q) => $q->orderByDesc('data_immatricolazione'),
        ]);

        $schema = TenantContext::tenant()?->getClienteCustomFieldsSchema() ?? [];

        return Inertia::render('Clienti/Show', [
            'cliente' => $cliente,
            'schema'  => $schema,
        ]);
    }

    public function create(): Response
    {
        $schema = TenantContext::tenant()?->getClienteCustomFieldsSchema() ?? [];

        return Inertia::render('Clienti/Edit', [
            'cliente' => null,
            'schema'  => $schema,
        ]);
    }

    public function store(StoreClienteRequest $request): JsonResponse|RedirectResponse
    {
        $cliente = Cliente::create($request->validated());

        // Risposta JSON per la modale di creazione rapida da Pratica
        if ($request->expectsJson()) {
            return response()->json([
                'cliente' => $cliente->only(['id', 'nome', 'telefono', 'email']),
            ], 201);
        }

        return redirect()
            ->route('clienti.show', $cliente)
            ->with('success', "Cliente \"{$cliente->nome}\" creato.");
    }

    public function edit(Request $request, Cliente $cliente): Response
    {
        $cliente->load(['veicoli' => fn ($q) => $q->orderByRaw('targa is null')->orderBy('targa')->select(['cliente_id', ...VeicoloController::CAMPI])]);

        $schema = TenantContext::tenant()?->getClienteCustomFieldsSchema() ?? [];

        return Inertia::render('Clienti/Edit', [
            'cliente' => $cliente,
            'schema'  => $schema,
            'ritorno' => $this->ritorno($request),
        ]);
    }

    public function update(UpdateClienteRequest $request, Cliente $cliente): RedirectResponse
    {
        $cliente->update($request->validated());

        // Si torna da dove si era partiti (es. il sinistro), altrimenti alla scheda cliente.
        $ritorno = $this->ritorno($request);

        return ($ritorno ? redirect($ritorno) : redirect()->route('clienti.show', $cliente))
            ->with('success', 'Cliente aggiornato.');
    }

    /** Pagina da cui si è arrivati: solo percorsi interni all'app, mai URL esterni. */
    private function ritorno(Request $request): ?string
    {
        $url = (string) $request->input('ritorno', '');

        return preg_match('#^/(?![/\\\\])[^\s]{0,500}$#', $url) ? $url : null;
    }

    public function destroy(Cliente $cliente): RedirectResponse
    {
        if ($cliente->pratiche()->exists()) {
            return redirect()
                ->back()
                ->with('error', 'Impossibile eliminare un cliente con pratiche associate.');
        }

        $nome = $cliente->nome;
        $cliente->delete();

        return redirect()
            ->route('clienti.index')
            ->with('success', "Cliente \"{$nome}\" eliminato.");
    }
}
