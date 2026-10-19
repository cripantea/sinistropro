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
                Rule::exists('contatti', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereJsonContains('tags', 'perito')),
            ],
            // Il veicolo deve essere del cliente scelto; obbligatorio per i tenant con la funzione "veicoli" (es. Malacrida).
            'veicolo_id' => [
                TenantContext::tenant()?->hasFeature('veicoli') ? 'required' : 'nullable',
                'integer',
                Rule::exists('veicoli', 'id')->where('tenant_id', $tenantId)->where('cliente_id', (int) $this->input('cliente_id')),
            ],
            'compagnia' => ['nullable', 'string', 'max:255'],
            'conducente_nome' => ['nullable', 'string', 'max:255'],
            'conducente_telefono' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'cliente_id.required' => 'Seleziona il cliente.',
            'cliente_id.exists' => 'Cliente non valido per questo tenant.',
            'perito_contatto_id.exists' => 'Perito non valido per questo tenant.',
            'veicolo_id.required' => 'Seleziona il veicolo (o aggiungine uno nuovo).',
            'veicolo_id.exists' => 'Il veicolo non appartiene al cliente scelto.',
        ];
    }
}
