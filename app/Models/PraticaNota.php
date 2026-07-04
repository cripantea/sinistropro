<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PraticaNota extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'pratica_note';

    protected $fillable = [
        'pratica_id',
        'tenant_id',
        'user_id',
        'nota',
    ];

    public function pratica(): BelongsTo
    {
        return $this->belongsTo(Pratica::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
