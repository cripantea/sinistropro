<?php

namespace App\Http\Requests\PraticaNota;

use App\Models\Pratica;
use Illuminate\Foundation\Http\FormRequest;

class StorePraticaNotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Pratica $pratica */
        $pratica = $this->route('pratica');

        // Il TenantScope + route model binding garantiscono già che la pratica
        // appartenga al tenant dell'utente. La doppia verifica qui protegge
        // da chiamate dirette all'endpoint che bypassino il route binding.
        return $pratica !== null
            && $pratica->tenant_id === auth()->user()->tenant_id;
    }

    public function rules(): array
    {
        return [
            'nota' => ['required', 'string', 'min:1', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'nota.required' => 'Il testo della nota è obbligatorio.',
            'nota.max'      => 'La nota non può superare i 5000 caratteri.',
        ];
    }
}
