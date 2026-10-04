<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailLog extends Model
{
    use BelongsToTenant;

    protected $table = 'email_logs';

    protected $fillable = [
        'tenant_id', 'pratica_id', 'automation_id', 'tipo',
        'to_address', 'cc_addresses', 'subject', 'status', 'error',
    ];

    protected $casts = ['cc_addresses' => 'array'];

    public function pratica(): BelongsTo
    {
        return $this->belongsTo(Pratica::class);
    }

    /**
     * Scrive una riga di log senza mai far fallire il chiamante.
     * Funziona anche nei job (nessun utente autenticato): tenant_id va sempre passato.
     */
    public static function registra(array $attributes): void
    {
        try {
            static::create($attributes);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('EmailLog: scrittura log fallita', ['errore' => $e->getMessage()]);
        }
    }
}
