<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Perito o carrozzeria: anagrafica esterna assegnabile a un sinistro,
 * senza bisogno di un account utente.
 */
class Contatto extends Model
{
    use BelongsToTenant;

    public const TIPI = ['perito', 'carrozzeria'];

    protected $table = 'contatti';

    protected $fillable = ['tenant_id', 'tipo', 'nome', 'telefono', 'email', 'note', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }
}
