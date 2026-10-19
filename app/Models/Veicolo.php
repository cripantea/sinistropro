<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Veicolo extends Model
{
    use BelongsToTenant;

    protected $table = 'veicoli';

    protected $fillable = [
        'tenant_id',
        'cliente_id',
        'targa',
        'telaio',
        'marca',
        'modello',
        'descrizione',
        'data_immatricolazione',
        'scadenza_revisione',
        'km',
        'ultima_visita',
        'custom_fields',
    ];

    protected $casts = [
        'data_immatricolazione' => 'date:Y-m-d',
        'scadenza_revisione'    => 'date:Y-m-d',
        'ultima_visita'         => 'date:Y-m-d',
        'km'                    => 'integer',
        'custom_fields'         => 'array',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function pratiche(): HasMany
    {
        return $this->hasMany(Pratica::class);
    }
}
