<?php

namespace App\Http\Requests\Pratica;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePraticaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && TenantContext::id() !== null;
    }

    public function rules(): array
    {
        $tenantId = TenantContext::id();

        return [
            'cliente_id' => [
                'required',
                'integer',
                Rule::exists('clienti', 'id')->where('tenant_id', $tenantId),
            ],
            'perito_contatto_id' => [
                'nullable',
                'integer',
                Rule::exists('contatti', 'id')->where('tenant_id', $tenantId)->where('tipo', 'perito'),
            ],
            'compagnia' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'cliente_id.required' => 'Seleziona il cliente.',
            'cliente_id.exists' => 'Cliente non valido per questo tenant.',
            'perito_contatto_id.exists' => 'Perito non valido per questo tenant.',
        ];
    }
}
