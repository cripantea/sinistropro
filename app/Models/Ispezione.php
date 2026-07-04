<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ispezione extends Model
{
    use BelongsToTenant;

    protected $table = 'ispezioni';

    protected $fillable = [
        'tenant_id',
        'pratica_id',
        'assegnato_a_user_id',
        'stato',
        'data_appuntamento',
        'note_sopralluogo',
        'dati_ispezione',
    ];

    protected $casts = [
        'data_appuntamento' => 'datetime',
        'dati_ispezione'    => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function pratica(): BelongsTo
    {
        return $this->belongsTo(Pratica::class);
    }

    public function assegnatoa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assegnato_a_user_id');
    }
}
