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

    protected $hidden = ['file_id', 'righe'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Righe del file già lette, salvate compresse nel database al caricamento (non su disco:
     * il sito e il worker della coda girano con utenti diversi e non leggono gli stessi file privati).
     */
    public function salvaRighe(array $rows): void
    {
        $this->forceFill(['righe' => base64_encode(gzcompress(json_encode($rows, JSON_UNESCAPED_UNICODE), 6))])->save();
    }

    /** @return array<int, array<string, mixed>>|null  null se le righe non ci sono più */
    public function leggiRighe(): ?array
    {
        $raw = $this->attributes['righe'] ?? null;
        if (! is_string($raw) || $raw === '') {
            return null;
        }
        $json = @gzuncompress((string) base64_decode($raw, true));
        $rows = $json === false ? null : json_decode($json, true);

        return is_array($rows) ? $rows : null;
    }

    /** I dati personali del file non restano salvati oltre il necessario. */
    public function cancellaRighe(): void
    {
        $this->forceFill(['righe' => null])->save();
    }

    /** Vecchio percorso su disco (import fatti prima che le righe passassero dal database). */
    public function rowsPath(): string
    {
        return "importazioni/{$this->file_id}.json";
    }
}
