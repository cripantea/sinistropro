<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class DocumentCategory extends Model
{
    protected $table = 'document_categories';

    protected $fillable = ['name', 'description'];

    public function allegati(): HasMany
    {
        return $this->hasMany(Allegato::class);
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_document_categories')
            ->withPivot('max_file_size_mb', 'is_enabled')
            ->withTimestamps();
    }

    public function automations(): BelongsToMany
    {
        return $this->belongsToMany(
            Automation::class,
            'automation_document_categories',
            'document_category_id',
            'automation_id'
        );
    }
}
