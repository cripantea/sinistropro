<?php

namespace App\Http\Requests\Cliente;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

class UpdateClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && TenantContext::id() !== null;
    }

    public function rules(): array
    {
        return [
            'nome'          => ['required', 'string', 'max:255'],
            'telefono'      => ['nullable', 'string', 'max:30'],
            'email'         => ['nullable', 'email', 'max:255'],
            'custom_fields' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required' => 'Il nome del cliente è obbligatorio.',
            'email.email'   => 'Inserisci un indirizzo email valido.',
        ];
    }
}
