<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ListaValori extends Model
{
    use BelongsToTenant;

    protected $table = 'lista_valori';

    protected $fillable = ['tenant_id', 'nome', 'slug', 'items'];

    protected $casts = ['items' => 'array'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function makeSlug(string $nome): string
    {
        return Str::slug($nome, '_');
    }
}
