<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModuleTemplate extends Model
{
    use BelongsToTenant;

    protected $table = 'module_templates';

    protected $fillable = [
        'tenant_id',
        'name',
        'pdf_template_s3_key',
        'output_document_category_id',
        'fields_schema',
    ];

    protected $casts = [
        'fields_schema' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function praticaModules(): HasMany
    {
        return $this->hasMany(PraticaModule::class, 'module_template_id');
    }
}
