<?php

namespace App\Http\Controllers;

use App\Models\Contatto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Anagrafica periti e carrozzerie. Sono due categorie distinte (campo `tipo`)
 * e non richiedono un account utente.
 */
class ContattoController extends Controller
{
    public function index(): Response
    {
        $this->authorizeAccess();

        return Inertia::render('Contatti/Index', [
            'contatti' => Contatto::orderBy('nome')->get(['id', 'tipo', 'nome', 'telefono', 'email', 'note', 'is_active']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

        $data = $this->validated($request);
        $contatto = Contatto::create($data + ['is_active' => true]);

        return back()->with('success', ucfirst($contatto->tipo)." \"{$contatto->nome}\" aggiunto.");
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

    /** @return array{tipo: string, nome: string, telefono: ?string, email: ?string, note: ?string} */
    private function validated(Request $request): array
    {
        return $request->validate([
            'tipo'     => ['required', Rule::in(Contatto::TIPI)],
            'nome'     => ['required', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email'    => ['nullable', 'email', 'max:255'],
            'note'     => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
