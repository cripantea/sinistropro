<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Template WhatsApp del numero dell'agenzia. Il testo per Meta usa {{1}}, {{2}}…; "variabili" dice
 * quale dato di SinistroPro va in ogni posizione (es. {"1": "{nome_cliente}"}).
 */
class WhatsappTemplate extends Model
{
    use BelongsToTenant;

    public const STATI = [
        'APPROVED' => 'Approvato',
        'PENDING' => 'In revisione',
        'REJECTED' => 'Rifiutato',
        'DISABLED' => 'Disattivato',
        'PAUSED' => 'In pausa',
    ];

    protected $fillable = [
        'tenant_id', 'external_id', 'name', 'language', 'category', 'status', 'status_reason',
        'body', 'corpo', 'variabili', 'variable_count', 'header_text', 'footer_text',
    ];

    protected $casts = ['variabili' => 'array', 'variable_count' => 'integer'];

    public function scopeApprovati($query)
    {
        return $query->where('status', 'APPROVED');
    }

    public function approvato(): bool
    {
        return $this->status === 'APPROVED';
    }

    /** Valori per {{1}}, {{2}}…: dai codici salvati (es. "{nome_cliente}") o da quelli passati. */
    public function parametri(?array $codici = null): array
    {
        $codici ??= $this->variabili ?? [];
        // Elenco semplice (dall'automazione: posizione 0 = {{1}}) o mappa {"1": …} (dal template).
        $elenco = array_is_list($codici);

        return collect(range(1, max(0, $this->variable_count)))
            ->map(fn ($i) => (string) ($elenco ? ($codici[$i - 1] ?? '') : ($codici[$i] ?? $codici[(string) $i] ?? '')))
            ->values()->all();
    }

    /** Testo finale come lo riceve il cliente. */
    public function rendi(array $valori): string
    {
        return preg_replace_callback('/\{\{(\d+)\}\}/', fn ($m) => (string) ($valori[(int) $m[1] - 1] ?? $m[0]), $this->body);
    }

    public function perUi(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'language' => $this->language,
            'category' => $this->category,
            'status' => $this->status,
            'statusLabel' => self::STATI[$this->status] ?? $this->status,
            'statusReason' => $this->status_reason,
            'body' => $this->body,
            'corpo' => $this->corpo,
            'variabili' => $this->parametri(),
            'variableCount' => $this->variable_count,
            'headerText' => $this->header_text,
            'footerText' => $this->footer_text,
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
