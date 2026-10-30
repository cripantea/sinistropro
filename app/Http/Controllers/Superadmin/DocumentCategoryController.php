<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\DocumentCategory;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
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
            'tenant_id'   => ['nullable', 'integer', 'exists:tenants,id'],
        ]);

        // L'elenco è comune a tutte le agenzie, ma una categoria nuova parte spenta per tutte:
        // la si accende solo dove serve (se creata dalla scheda di un tenant, lì è già accesa).
        DB::transaction(function () use ($data): void {
            $categoria = DocumentCategory::create(['name' => $data['name'], 'description' => $data['description'] ?? null]);
            $categoria->tenants()->attach(
                Tenant::pluck('id')->mapWithKeys(fn ($id) => [$id => [
                    'is_enabled'       => $id === (int) ($data['tenant_id'] ?? 0),
                    'max_file_size_mb' => 50,
                ]])->all()
            );
        });

        return back()->with('success', "Categoria \"{$data['name']}\" creata.");
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
