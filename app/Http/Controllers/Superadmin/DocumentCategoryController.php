<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\DocumentCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DocumentCategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Superadmin/DocumentCategories/Index', [
            'categories' => DocumentCategory::withCount('allegati')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100', 'unique:document_categories,name'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        DocumentCategory::create($data);

        return redirect()->route('superadmin.document-categories.index')
            ->with('success', "Categoria \"{$data['name']}\" creata.");
    }

    public function update(Request $request, DocumentCategory $documentCategory): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100', "unique:document_categories,name,{$documentCategory->id}"],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $documentCategory->update($data);

        return redirect()->route('superadmin.document-categories.index')
            ->with('success', "Categoria \"{$documentCategory->name}\" aggiornata.");
    }

    public function destroy(DocumentCategory $documentCategory): RedirectResponse
    {
        $documentCategory->delete();

        return redirect()->route('superadmin.document-categories.index')
            ->with('success', "Categoria eliminata.");
    }
}
