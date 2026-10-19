<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un import di clienti da file: bozza (caricato e mappato) → in_coda → in_corso → completata | fallita.
 */
class Importazione extends Model
{
    use BelongsToTenant;

    protected $table = 'importazioni';

    protected $fillable = [
        'tenant_id', 'user_id', 'file_name', 'file_id', 'stato',
        'mapping', 'opzioni', 'riepilogo', 'avvisi', 'errore', 'completata_at',
    ];

    protected $casts = [
        'mapping'       => 'array',
        'opzioni'       => 'array',
        'riepilogo'     => 'array',
        'avvisi'        => 'array',
        'completata_at' => 'datetime',
    ];

    protected $hidden = ['file_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Righe del file già lette e salvate in JSON al caricamento. */
    public function rowsPath(): string
    {
        return "importazioni/{$this->file_id}.json";
    }
}
