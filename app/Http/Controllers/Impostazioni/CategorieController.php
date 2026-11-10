<?php

namespace App\Http\Controllers\Impostazioni;

use App\Http\Controllers\Controller;
use App\Support\AccessoImpostazioni;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Impostazioni → Categorie documenti: quali categorie usa l'agenzia e la dimensione massima dei file. */
class CategorieController extends Controller
{
    public function show(): Response
    {
        $tenant = AccessoImpostazioni::tenant();

        return Inertia::render('Impostazioni/Categorie', [
            'categorie' => $tenant->categorieDocumenti(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = AccessoImpostazioni::tenant();
        $data = $request->validate([
            'categorie' => ['required', 'array'],
            'categorie.*.id' => ['required', 'integer', 'exists:document_categories,id'],
            'categorie.*.is_enabled' => ['required', 'boolean'],
            'categorie.*.max_file_size_mb' => ['required', 'integer', 'min:1', 'max:500'],
        ]);

        $tenant->documentCategories()->sync(collect($data['categorie'])->mapWithKeys(fn ($c) => [
            $c['id'] => ['is_enabled' => $c['is_enabled'], 'max_file_size_mb' => $c['max_file_size_mb']],
        ])->all());

        return back()->with('success', 'Categorie salvate.');
    }
}
