<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    use BelongsToTenant;

    protected $table = 'clienti';

    protected $fillable = [
        'tenant_id',
        'nome',
        'telefono',
        'email',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function pratiche(): HasMany
    {
        return $this->hasMany(Pratica::class);
    }
}
