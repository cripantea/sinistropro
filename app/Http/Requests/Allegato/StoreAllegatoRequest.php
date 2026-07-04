<?php

namespace App\Http\Requests\Allegato;

use Illuminate\Foundation\Http\FormRequest;

class StoreAllegatoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Il route model binding + TenantScope garantiscono già
        // che la pratica appartenga al tenant dell'utente corrente.
        return $this->route('pratica') !== null;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:512000', // hard global ceiling 500 MB; per-category limits enforced in controller
                'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,webp,zip',
            ],
            'document_category_id' => ['nullable', 'integer', 'exists:document_categories,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'È obbligatorio allegare un file.',
            'file.max'      => 'Il file non può superare i 50 MB.',
            'file.mimes'    => 'Formato file non supportato.',
        ];
    }
}
