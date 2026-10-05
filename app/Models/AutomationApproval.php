<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Promemoria programmato in attesa di conferma umana prima dell'invio.
 */
class AutomationApproval extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'automation_id', 'cliente_id', 'field_name', 'field_value', 'status', 'decided_by', 'decided_at'];

    protected $casts = ['decided_at' => 'datetime'];

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
