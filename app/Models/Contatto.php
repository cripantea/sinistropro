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

    /** Tag usati dal sinistro per scegliere perito e carrozzeria. */
    public const TAG_PERITO      = 'perito';
    public const TAG_CARROZZERIA = 'carrozzeria';

    protected $table = 'contatti';

    protected $fillable = ['tenant_id', 'nome', 'tags', 'telefono', 'email', 'note', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'tags' => 'array'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Contatti con un certo tag (es. "perito"). */
    public function scopeTag($query, string $tag)
    {
        return $query->whereJsonContains('tags', self::normalizeTag($tag));
    }

    public function hasTag(string $tag): bool
    {
        return in_array(self::normalizeTag($tag), $this->tags ?? [], true);
    }

    public static function normalizeTag(string $tag): string
    {
        return mb_substr(mb_strtolower(preg_replace('/\s+/u', ' ', trim($tag))), 0, 30);
    }

    /**
     * Tag puliti: minuscoli, senza duplicati né vuoti, al massimo 10.
     *
     * @return array<int, string>
     */
    public static function normalizeTags(mixed $tags): array
    {
        return collect(is_array($tags) ? $tags : [])
            ->filter(fn ($t) => is_string($t))
            ->map(fn (string $t) => self::normalizeTag($t))
            ->filter()->unique()->take(10)->values()->all();
    }
}
