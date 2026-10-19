<?php

namespace App\Http\Requests\Cliente;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && TenantContext::id() !== null;
    }

    protected function prepareForValidation(): void
    {
        foreach (['codice_fiscale', 'partita_iva', 'provincia'] as $k) {
            if (is_string($this->input($k))) {
                $this->merge([$k => strtoupper(str_replace(' ', '', $this->input($k))) ?: null]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'nome'          => ['required', 'string', 'max:255'],
            'telefono'      => ['nullable', 'string', 'max:30'],
            'email'         => ['nullable', 'email', 'max:255'],
            'codice_esterno' => ['nullable', 'string', 'max:50',
                Rule::unique('clienti', 'codice_esterno')->where('tenant_id', TenantContext::id())->ignore($this->route('cliente')?->id)],
            'codice_fiscale' => ['nullable', 'string', 'max:16'],
            'partita_iva'    => ['nullable', 'string', 'max:20'],
            'indirizzo'      => ['nullable', 'string', 'max:255'],
            'cap'            => ['nullable', 'string', 'max:10'],
            'citta'          => ['nullable', 'string', 'max:100'],
            'provincia'      => ['nullable', 'string', 'max:5'],
            'custom_fields' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required' => 'Il nome del cliente è obbligatorio.',
            'codice_esterno.unique' => 'Questo codice cliente è già usato da un altro cliente.',
            'email.email'   => 'Inserisci un indirizzo email valido.',
        ];
    }
}
