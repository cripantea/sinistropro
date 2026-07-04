<?php

namespace App\Http\Requests\Superadmin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->role === 'superadmin';
    }

    public function rules(): array
    {
        return [
            'name'                          => ['required', 'string', 'max:255', 'unique:tenants,name'],
            'default_notice_days'           => ['required', 'integer', 'min:1', 'max:365'],
            'custom_fields_schema'          => ['nullable', 'array', 'max:20'],
            'custom_fields_schema.*.name'   => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/'],
            'custom_fields_schema.*.label'  => ['required', 'string', 'max:100'],
            'custom_fields_schema.*.type'   => ['required', 'in:text,date,number,boolean'],
            'statuses'                      => ['nullable', 'array', 'max:20'],
            'statuses.*.name'               => ['required', 'string', 'max:100'],
            'statuses.*.color'              => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'statuses.*.is_closed'          => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique'                        => 'Esiste già un tenant con questo nome.',
            'custom_fields_schema.*.name.regex'  => 'Il nome del campo deve usare solo lettere minuscole, numeri e underscore.',
            'custom_fields_schema.*.type.in'     => 'Tipo campo non valido (text, date, number, boolean).',
            'statuses.*.color.regex'             => 'Il colore deve essere un hex valido (es. #3B82F6).',
        ];
    }
}
