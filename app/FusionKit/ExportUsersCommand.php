<?php

namespace App\FusionKit;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Esporta gli utenti per l'import in Fusion ID (hash bcrypt inclusi: nessuno deve
 * reimpostare la password). Il file contiene hash di password: tenerlo privato.
 *
 *   php artisan fusion:export-users > export-<prodotto>.json
 */
class ExportUsersCommand extends Command
{
    protected $signature = 'fusion:export-users';

    protected $description = 'Esporta gli utenti per Fusion ID (fusion-id/scripts/import-users.mjs)';

    public function handle(): int
    {
        $users = User::query()->orderBy('id')->get()
            ->filter(fn ($u) => ! isset($u->is_active) || $u->is_active)
            ->map(fn ($u) => [
                'email' => $u->email,
                'name' => $u->name,
                'passwordHash' => $u->password,
                'staff' => ($u->role ?? null) === 'superadmin',
                'emailVerified' => true,
            ])->values();

        $this->line(json_encode([
            'product' => config('fusionkit.product'),
            'exportedAt' => now()->toIso8601String(),
            'users' => $users,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
