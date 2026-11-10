<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Registro degli invii programmati: impedisce di mandare due volte lo stesso promemoria. */
class AutomationRun extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['automation_id', 'soggetto', 'soggetto_id', 'chiave'];

    /** true se è la prima volta (e la registra), false se era già partito. */
    public static function prima(Automation $automation, string $soggetto, int $id, string $chiave): bool
    {
        $creato = static::query()->insertOrIgnore([
            'automation_id' => $automation->id,
            'soggetto' => $soggetto,
            'soggetto_id' => $id,
            'chiave' => $chiave,
            'created_at' => now(),
        ]);

        return $creato === 1;
    }
}
