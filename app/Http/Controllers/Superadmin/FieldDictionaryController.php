<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\FieldDictionaryEntry;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FieldDictionaryController extends Controller
{
    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $this->validated($request, $tenant);

        FieldDictionaryEntry::create([
            'tenant_id' => $tenant->id,
            'key' => $data['key'],
            'label' => $data['label'],
            'type' => $data['type'],
            'source_type' => $data['source_type'],
            'source_field' => $data['source_field'] ?? null,
        ]);

        return redirect()
            ->to(route('superadmin.tenants.edit', $tenant).'?tab=dictionary')
            ->with('success', "Campo \"{$data['label']}\" aggiunto al dizionario.");
    }

    public function update(Request $request, Tenant $tenant, FieldDictionaryEntry $fieldDictionaryEntry): RedirectResponse
    {
        abort_unless($fieldDictionaryEntry->tenant_id === $tenant->id, 403);

        $data = $this->validated($request, $tenant, $fieldDictionaryEntry);

        $fieldDictionaryEntry->update([
            'key' => $data['key'],
            'label' => $data['label'],
            'type' => $data['type'],
            'source_type' => $data['source_type'],
            'source_field' => $data['source_field'] ?? null,
        ]);

        return redirect()
            ->to(route('superadmin.tenants.edit', $tenant).'?tab=dictionary')
            ->with('success', "Campo \"{$data['label']}\" aggiornato.");
    }

    public function destroy(Tenant $tenant, FieldDictionaryEntry $fieldDictionaryEntry): RedirectResponse
    {
        abort_unless($fieldDictionaryEntry->tenant_id === $tenant->id, 403);

        $label = $fieldDictionaryEntry->label;
        $fieldDictionaryEntry->delete();

        return redirect()
            ->to(route('superadmin.tenants.edit', $tenant).'?tab=dictionary')
            ->with('success', "Campo \"{$label}\" eliminato dal dizionario.");
    }

    /**
     * @return array{key: string, label: string, type: string, source_type: string, source_field: ?string}
     */
    private function validated(Request $request, Tenant $tenant, ?FieldDictionaryEntry $ignoring = null): array
    {
        $customFieldNames = array_column($tenant->getCustomFieldsSchema(), 'name');

        return $request->validate([
            'key' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('field_dictionary_entries', 'key')
                    ->where('tenant_id', $tenant->id)
                    ->ignore($ignoring?->id),
            ],
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['text', 'date', 'number', 'boolean'])],
            'source_type' => ['required', Rule::in(['manual', 'cliente', 'pratica_field'])],
            'source_field' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) use ($request, $customFieldNames): void {
                    $sourceType = $request->input('source_type');

                    if ($sourceType === 'cliente' && ! in_array($value, ['nome', 'telefono', 'email'], true)) {
                        $fail('Seleziona un campo cliente valido.');
                    }
                    if ($sourceType === 'pratica_field' && ! in_array($value, $customFieldNames, true)) {
                        $fail('Seleziona un campo pratica valido per questo tenant.');
                    }
                },
            ],
        ], [
            'key.regex' => 'La chiave deve usare solo lettere minuscole, numeri e underscore, e iniziare con una lettera.',
            'key.unique' => 'Esiste già un campo del dizionario con questa chiave.',
        ]);
    }
}
