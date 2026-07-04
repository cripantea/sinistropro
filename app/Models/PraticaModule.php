<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PraticaModule extends Model
{
    protected $table = 'pratica_modules';

    protected $fillable = [
        'pratica_id',
        'module_template_id',
        'values',
    ];

    protected $casts = [
        'values' => 'array',
    ];

    public function pratica(): BelongsTo
    {
        return $this->belongsTo(Pratica::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ModuleTemplate::class, 'module_template_id');
    }
}
