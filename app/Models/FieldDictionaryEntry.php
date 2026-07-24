<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldDictionaryEntry extends Model
{
    use BelongsToTenant;

    protected $table = 'field_dictionary_entries';

    protected $fillable = [
        'tenant_id',
        'key',
        'label',
        'type',
        'source_type',
        'source_field',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
