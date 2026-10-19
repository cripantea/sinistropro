<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldDictionaryEntry extends Model
{
    use BelongsToTenant;

    protected $table = 'field_dictionary_entries';

    /** Campi dell'anagrafica cliente usabili come sorgente di autocompilazione dei moduli. */
    public const CLIENTE_SOURCES = ['nome', 'telefono', 'email', 'indirizzo', 'cap', 'citta', 'provincia', 'codice_fiscale', 'partita_iva'];

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
