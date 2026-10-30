<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Valori di sistema modificabili dal superadmin, salvati nella tabella "impostazioni". */
class Impostazioni
{
    public static function get(string $chiave, ?string $default = null): ?string
    {
        return DB::table('impostazioni')->where('chiave', $chiave)->value('valore') ?? $default;
    }

    public static function set(string $chiave, ?string $valore): void
    {
        DB::table('impostazioni')->updateOrInsert(
            ['chiave' => $chiave],
            ['valore' => $valore, 'updated_at' => now(), 'created_at' => now()],
        );
    }
}
