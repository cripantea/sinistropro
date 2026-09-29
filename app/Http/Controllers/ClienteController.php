<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cliente\StoreClienteRequest;
use App\Http\Requests\Cliente\UpdateClienteRequest;
use App\Models\Cliente;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClienteController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search', '');
        $schema = TenantContext::tenant()?->getClienteCustomFieldsSchema() ?? [];

        $clienti = Cliente::withCount('pratiche')
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                  ->orWhere('telefono', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
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

    public function show(Cliente $cliente): Response
    {
        $cliente->loadCount('pratiche');
        $cliente->load([
            'pratiche' => fn ($q) => $q->with('currentStatus:id,name,color')->latest()->limit(10),
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

    public function edit(Cliente $cliente): Response
    {
        $schema = TenantContext::tenant()?->getClienteCustomFieldsSchema() ?? [];

        return Inertia::render('Clienti/Edit', [
            'cliente' => $cliente,
            'schema'  => $schema,
        ]);
    }

    public function update(UpdateClienteRequest $request, Cliente $cliente): RedirectResponse
    {
        $cliente->update($request->validated());

        return redirect()
            ->route('clienti.show', $cliente)
            ->with('success', 'Cliente aggiornato.');
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
