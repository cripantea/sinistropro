<?php

namespace App\Services;

use App\Models\ListaValori;
use App\Models\Tenant;
use App\Models\TenantStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Crea un'agenzia pronta all'uso (stati e compagnie standard) con il suo amministratore.
 * Usata dalla registrazione da soli e dal superadmin; il resto lo configura il cliente dopo.
 * Paga con Stripe: finché non c'è un abbonamento è in sola lettura.
 */
class NuovaAgenzia
{
    public const STATI = [
        ['Aperto', '#6366F1', false, true],
        ['In perizia', '#F59E0B', false, false],
        ['Attesa documenti', '#F97316', false, false],
        ['In liquidazione', '#3B82F6', false, false],
        ['Chiuso', '#10B981', true, false],
    ];

    public const COMPAGNIE = ['Allianz', 'AXA', 'Cattolica', 'Generali', 'Groupama', 'HDI', 'Itas', 'Reale Mutua', 'UnipolSai', 'Vittoria', 'Zurich'];

    /** @param bool $passwordDaCambiare true se la password l'ha scelta il superadmin: al primo accesso il cliente ne sceglie una sua. */
    public static function crea(string $agenzia, string $nome, string $email, string $password, bool $passwordDaCambiare = false): User
    {
        return DB::transaction(function () use ($agenzia, $nome, $email, $password, $passwordDaCambiare): User {
            $tenant = new Tenant([
                'name'     => trim($agenzia),
                'settings' => [
                    'default_notice_days' => 14,
                    'features'            => ['clienti' => true],
                ],
            ]);
            $tenant->billing_mode = 'stripe';
            $tenant->save();

            foreach (self::STATI as $i => [$stato, $colore, $chiuso, $iniziale]) {
                TenantStatus::create([
                    'tenant_id' => $tenant->id, 'name' => $stato, 'color' => $colore, 'order' => $i,
                    'is_closed' => $chiuso, 'is_initial' => $iniziale, 'send_email_notification' => false,
                ]);
            }

            ListaValori::create(['tenant_id' => $tenant->id, 'nome' => 'Compagnie', 'slug' => 'compagnie', 'items' => self::COMPAGNIE]);

            $user = new User([
                'name'      => trim($nome),
                'email'     => strtolower(trim($email)),
                'password'  => $password,
                'role'      => 'tenant-admin',
                'tenant_id' => $tenant->id,
                'is_active' => true,
            ]);
            // Niente verifica email: l'account nasce da una chiamata o da un pagamento imminente.
            $user->email_verified_at = now();
            $user->must_change_password = $passwordDaCambiare;
            $user->save();

            return $user;
        });
    }
}
