<?php

namespace App\Http\Requests\Superadmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->role === 'superadmin';
    }

    public function rules(): array
    {
        $tenantId = $this->route('tenant')?->id;

        return [
            'name'                          => ['required', 'string', 'max:255', Rule::unique('tenants')->ignore($tenantId)],
            'default_notice_days'           => ['required', 'integer', 'min:1', 'max:365'],
            'custom_fields_schema'          => ['nullable', 'array', 'max:20'],
            'custom_fields_schema.*.name'   => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/'],
            'custom_fields_schema.*.label'  => ['required', 'string', 'max:100'],
            'custom_fields_schema.*.type'   => ['required', 'in:text,date,number,boolean'],
            'custom_fields_schema.*.required' => ['boolean'],
            'statuses'                      => ['nullable', 'array', 'max:20'],
            'statuses.*.id'                 => ['nullable', 'integer', 'exists:tenant_statuses,id'],
            'statuses.*.name'               => ['required', 'string', 'max:100'],
            'statuses.*.color'              => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'statuses.*.is_closed'          => ['boolean'],
            'statuses.*.is_initial'         => ['boolean'],
        ];
    }
}
