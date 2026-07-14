<?php

namespace App\Http\Requests\Pratica;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePraticaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->tenant_id !== null;
    }

    public function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'cliente_id' => [
                'required',
                'integer',
                Rule::exists('clienti', 'id')->where('tenant_id', $tenantId),
            ],
            'perito_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId)->where('role', 'external'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'cliente_id.required'      => 'Seleziona il cliente.',
            'cliente_id.exists'        => 'Cliente non valido per questo tenant.',
            'perito_user_id.required'  => 'Seleziona il perito.',
            'perito_user_id.exists'    => 'Perito non valido per questo tenant.',
        ];
    }
}
