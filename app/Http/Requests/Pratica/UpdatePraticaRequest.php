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

        return [
            'current_status_id' => [
                'nullable',
                'exists:tenant_statuses,id',
                function ($attr, $value, $fail) use ($tenantId) {
                    if ($value && ! \App\Models\TenantStatus::where('id', $value)->where('tenant_id', $tenantId)->exists()) {
                        $fail('Stato non valido per questo tenant.');
                    }
                },
            ],
            'custom_fields'   => ['nullable', 'array'],
            'custom_fields.*' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
