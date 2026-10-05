<?php

namespace App\Http\Controllers;

use App\Models\Contatto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Rubrica dei destinatari (periti, carrozzerie, e qualsiasi altro tag): contatti con
 * nome, telefono, email e tag liberi. Non richiedono un account utente.
 */
class ContattoController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeAccess();

        $contatti = Contatto::orderBy('nome')->get(['id', 'nome', 'tags', 'telefono', 'email', 'note', 'is_active']);

        return Inertia::render('Contatti/Index', [
            'contatti' => $contatti,
            // Tag in uso (per filtro e suggerimenti): perito e carrozzeria sempre disponibili.
            'tags'     => $contatti->pluck('tags')->flatten()->merge([Contatto::TAG_PERITO, Contatto::TAG_CARROZZERIA])->unique()->sort()->values(),
            'tag'      => $request->filled('tag') ? Contatto::normalizeTag((string) $request->input('tag')) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

        $data = $this->validated($request);
        $contatto = Contatto::create($data + ['is_active' => true]);

        return back()->with('success', "\"{$contatto->nome}\" aggiunto alla rubrica.");
    }

    public function update(Request $request, Contatto $contatto): RedirectResponse
    {
        $this->authorizeAccess();

        $data = $this->validated($request);
        $contatto->update($data + ['is_active' => $request->boolean('is_active', $contatto->is_active)]);

        return back()->with('success', "\"{$contatto->nome}\" aggiornato.");
    }

    public function destroy(Contatto $contatto): RedirectResponse
    {
        $this->authorizeAccess();

        // Le ispezioni collegate restano: la FK è nullOnDelete.
        $nome = $contatto->nome;
        $contatto->delete();

        return back()->with('success', "\"{$nome}\" eliminato.");
    }

    private function authorizeAccess(): void
    {
        abort_if(auth()->user()->role === 'external', 403);
    }

    /** @return array{nome: string, tags: array, telefono: ?string, email: ?string, note: ?string} */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'nome'     => ['required', 'string', 'max:255'],
            'tags'     => ['nullable', 'array', 'max:10'],
            'tags.*'   => ['string', 'max:30'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email'    => ['nullable', 'email', 'max:255'],
            'note'     => ['nullable', 'string', 'max:2000'],
        ]);

        $data['tags'] = Contatto::normalizeTags($data['tags'] ?? []);

        return $data;
    }
}
