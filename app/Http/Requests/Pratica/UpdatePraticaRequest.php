<?php

namespace App\Http\Requests\Pratica;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePraticaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Il TenantScope garantisce già che la pratica appartenga al tenant.
        return auth()->check();
    }

    public function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;

        $rules = [
            'current_status_id' => [
                'nullable',
                'exists:tenant_statuses,id',
                function ($attr, $value, $fail) use ($tenantId) {
                    if ($value && ! \App\Models\TenantStatus::where('id', $value)->where('tenant_id', $tenantId)->exists()) {
                        $fail('Stato non valido per questo tenant.');
                    }
                },
            ],
            'custom_fields' => ['nullable', 'array'],
        ];

        foreach (auth()->user()->tenant?->getCustomFieldsSchema() ?? [] as $field) {
            $rules['custom_fields.' . $field['name']] = [
                ($field['required'] ?? false) ? 'required' : 'nullable',
                'string',
                'max:1000',
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        $messages = [];

        foreach (auth()->user()->tenant?->getCustomFieldsSchema() ?? [] as $field) {
            if ($field['required'] ?? false) {
                $messages['custom_fields.' . $field['name'] . '.required'] = "Il campo \"{$field['label']}\" è obbligatorio.";
            }
        }

        return $messages;
    }
}
