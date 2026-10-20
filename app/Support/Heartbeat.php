<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Battito dei comandi automatici (scheduler): ognuno segna quando ha girato,
 * così se il cron smette di lanciarli lo si vede subito nell'app.
 */
class Heartbeat
{
    public static function segna(string $nome): void
    {
        DB::table('scheduler_heartbeats')->updateOrInsert(['name' => $nome], ['ran_at' => now()]);
    }

    /** Ultimo giro, se è più vecchio di $ore; null se è tutto regolare (o se non si sa). */
    public static function fermoDa(string $nome, int $ore = 26): ?Carbon
    {
        try {
            $ultimo = DB::table('scheduler_heartbeats')->where('name', $nome)->value('ran_at');
        } catch (\Throwable) {
            return null;
        }
        if (! $ultimo) {
            return null;
        }
        $ultimo = Carbon::parse($ultimo);

        return $ultimo->lt(now()->subHours($ore)) ? $ultimo : null;
    }
}
