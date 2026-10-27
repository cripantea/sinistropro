<?php

namespace App\Http\Requests\Superadmin;

use Illuminate\Foundation\Http\FormRequest;

/** Nuova agenzia dal superadmin: solo nome e account del referente, il resto si configura dopo. */
class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->role === 'superadmin';
    }

    public function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'max:255', 'unique:tenants,name'],
            'admin_name'     => ['nullable', 'string', 'max:255'],
            'admin_email'    => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['required', 'string', 'min:8', 'max:100'],
            // Prezzi personalizzati in euro IVA inclusa; vuoto = standard (come la scheda Abbonamento).
            'price_monthly'  => ['nullable', 'numeric', 'min:1', 'max:100000'],
            'price_yearly'   => ['nullable', 'numeric', 'min:1', 'max:1000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique'        => 'Esiste già un\'agenzia con questo nome.',
            'admin_email.unique' => 'Questa email ha già un account.',
        ];
    }
}
