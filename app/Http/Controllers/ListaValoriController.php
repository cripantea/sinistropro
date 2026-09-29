<?php

namespace App\Http\Controllers;

use App\Models\ListaValori;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ListaValoriController extends Controller
{
    public function index(): Response
    {
        $liste = ListaValori::orderBy('nome')->get();

        return Inertia::render('ListaValori/Index', [
            'liste' => $liste,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nome'  => ['required', 'string', 'max:100'],
            'items' => ['nullable', 'array'],
            'items.*' => ['string', 'max:255'],
        ]);

        $slug = ListaValori::makeSlug($data['nome']);

        $exists = ListaValori::acrossAllTenants()
            ->where('tenant_id', TenantContext::id())
            ->where('slug', $slug)
            ->exists();

        if ($exists) {
            return back()->withErrors(['nome' => 'Esiste già una lista con questo nome.']);
        }

        ListaValori::create([
            'nome'  => $data['nome'],
            'slug'  => $slug,
            'items' => array_values(array_filter($data['items'] ?? [], fn ($v) => trim($v) !== '')),
        ]);

        return back()->with('success', "Lista \"{$data['nome']}\" creata.");
    }

    public function update(Request $request, ListaValori $listaValori): RedirectResponse
    {
        $data = $request->validate([
            'nome'    => ['required', 'string', 'max:100'],
            'items'   => ['nullable', 'array'],
            'items.*' => ['string', 'max:255'],
        ]);

        $listaValori->update([
            'nome'  => $data['nome'],
            'items' => array_values(array_filter($data['items'] ?? [], fn ($v) => trim($v) !== '')),
        ]);

        return back()->with('success', "Lista aggiornata.");
    }

    public function destroy(ListaValori $listaValori): RedirectResponse
    {
        $nome = $listaValori->nome;
        $listaValori->delete();

        return back()->with('success', "Lista \"{$nome}\" eliminata.");
    }

    /** JSON endpoint per usare i valori di una lista in form dinamici. */
    public function show(string $slug): JsonResponse
    {
        $lista = ListaValori::acrossAllTenants()
            ->where('tenant_id', TenantContext::id())
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json(['items' => $lista->items ?? []]);
    }
}
