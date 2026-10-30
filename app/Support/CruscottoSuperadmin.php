<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Numeri della dashboard superadmin: incasso, uso reale e salute di ogni agenzia.
 *
 * L'uso si misura dal registro attività (audit_logs): ogni sinistro aperto, visto o modificato.
 * Non contano i superadmin né le azioni fatte "Accedi come", così il nostro supporto non gonfia i numeri.
 * Gli account demo (settings.demo) stanno fuori da incassi e salute: servono solo a contare le demo.
 */
class CruscottoSuperadmin
{
    public const GIORNI_INATTIVA = 14;

    public static function dati(): array
    {
        $tenants = Tenant::orderBy('name')->get();
        [$demo, $clienti] = $tenants->partition(fn (Tenant $t) => (bool) $t->getSetting('demo'));

        $attivita = self::attivitaPerTenant();
        $righe = $clienti->map(fn (Tenant $t) => self::riga($t, $attivita))->sortBy('salute')->values();

        $paganti = $righe->filter(fn ($r) => $r['mensile_cents'] > 0);

        return [
            'incasso_mensile_cents' => $paganti->sum('mensile_cents'),
            'obiettivo_cents'       => (int) Impostazioni::get('obiettivo_mensile_cents', '0'),
            'agenzie_paganti'       => $paganti->count(),
            'agenzie_totali'        => $righe->count(),
            'utenti_attivi_7'       => $righe->sum('utenti_attivi_7'),
            'utenti_totali'         => $righe->sum('utenti_totali'),
            'azioni_7'              => $righe->sum('azioni_7'),
            'sinistri_30'           => $righe->sum('sinistri_30'),
            'nuove_agenzie_30'      => $clienti->filter(fn (Tenant $t) => $t->created_at->gte(now()->subDays(30)))->count(),
            'demo_30'               => self::giorniDemo($demo->pluck('id')),
            'inattive'              => $righe->filter(fn ($r) => $r['inattiva'])->count(),
            'agenzie'               => $righe,
        ];
    }

    /** Ultima attività, azioni e utenti attivi per tenant (solo utenti veri dell'agenzia). */
    private static function attivitaPerTenant(): Collection
    {
        $base = fn () => DB::table('audit_logs')
            ->join('users', 'users.id', '=', 'audit_logs.user_id')
            ->where('users.role', '!=', 'superadmin')
            ->whereNull('audit_logs.impersonated_by_user_id')
            ->whereNotNull('audit_logs.tenant_id');

        $ultima = $base()->groupBy('audit_logs.tenant_id')
            ->selectRaw('audit_logs.tenant_id, max(audit_logs.created_at) as ultima')
            ->pluck('ultima', 'tenant_id');

        $settimana = $base()->where('audit_logs.created_at', '>=', now()->subDays(7))
            ->groupBy('audit_logs.tenant_id')
            ->selectRaw('audit_logs.tenant_id, count(*) as azioni, count(distinct audit_logs.user_id) as utenti')
            ->get()->keyBy('tenant_id');

        $sinistri = DB::table('pratiche')->where('created_at', '>=', now()->subDays(30))
            ->groupBy('tenant_id')->selectRaw('tenant_id, count(*) as n')->pluck('n', 'tenant_id');

        $utenti = DB::table('users')->where('is_active', true)->where('role', '!=', 'superadmin')
            ->whereNotNull('tenant_id')->groupBy('tenant_id')->selectRaw('tenant_id, count(*) as n')->pluck('n', 'tenant_id');

        $automazioni = DB::table('automations')->where('is_active', true)
            ->groupBy('tenant_id')->selectRaw('tenant_id, count(*) as n')->pluck('n', 'tenant_id');

        return collect([
            'ultima'      => $ultima,
            'settimana'   => $settimana,
            'sinistri'    => $sinistri,
            'utenti'      => $utenti,
            'automazioni' => $automazioni,
            'email'       => DB::table('tenant_mail_settings')->where('is_active', true)->pluck('tenant_id')->flip(),
            'whatsapp'    => DB::table('whatsapp_sessions')->where('status', 'active')->pluck('tenant_id')->flip(),
        ]);
    }

    private static function riga(Tenant $t, Collection $a): array
    {
        $stato      = $t->billingState();
        $ultima     = isset($a['ultima'][$t->id]) ? Carbon::parse($a['ultima'][$t->id]) : null;
        $giorni     = $ultima ? (int) $ultima->diffInDays(now()) : null;
        $utentiTot  = (int) ($a['utenti'][$t->id] ?? 0);
        $utenti7    = (int) ($a['settimana'][$t->id]->utenti ?? 0);
        $azioni7    = (int) ($a['settimana'][$t->id]->azioni ?? 0);
        $sinistri30 = (int) ($a['sinistri'][$t->id] ?? 0);
        $automaz    = (int) ($a['automazioni'][$t->id] ?? 0);
        $email      = isset($a['email'][$t->id]);
        $whatsapp   = isset($a['whatsapp'][$t->id]);

        // Punteggio 0–100, in quattro parti leggibili:
        $voci = [
            // usato di recente (40)
            'recente'   => match (true) { $giorni === null => 0, $giorni <= 2 => 40, $giorni <= 7 => 30, $giorni <= 14 => 15, default => 0 },
            // quanti dei suoi utenti lo usano davvero (20)
            'utenti'    => $utentiTot > 0 ? (int) round(min($utenti7 / $utentiTot, 1) * 20) : 0,
            // ci lavora sopra: sinistri aperti nel mese (20, pieno da 5 in su)
            'sinistri'  => (int) round(min($sinistri30 / 5, 1) * 20),
            // l'ha configurato: email, WhatsApp, automazioni (20)
            'setup'     => ($email ? 7 : 0) + ($whatsapp ? 7 : 0) + ($automaz > 0 ? 6 : 0),
        ];
        $salute = array_sum($voci);
        // Pagamento fallito: comunque a rischio, qualunque sia l'uso.
        if ($stato === 'in_ritardo') {
            $salute = min($salute, 40);
        }

        return [
            'id'              => $t->id,
            'nome'            => $t->name,
            'stato'           => $stato,
            'mensile_cents'   => self::mensileCents($t, $stato),
            'prezzo_mancante' => $stato === 'esterno' && $t->price_monthly_cents === null,
            'utenti_totali'   => $utentiTot,
            'utenti_attivi_7' => $utenti7,
            'azioni_7'        => $azioni7,
            'sinistri_30'     => $sinistri30,
            'ultima_attivita' => $ultima?->toIso8601String(),
            'giorni_fermo'    => $giorni,
            'email'           => $email,
            'whatsapp'        => $whatsapp,
            'automazioni'     => $automaz,
            'salute'          => $salute,
            'voci_salute'     => $voci,
            'inattiva'        => $giorni === null || $giorni >= self::GIORNI_INATTIVA,
        ];
    }

    /**
     * Quanto incassiamo al mese da questa agenzia (IVA inclusa). Annuale diviso 12.
     * "Fatturato a parte" vale il prezzo mensile impostato nella scheda; senza prezzo conta 0.
     * Pagamento fallito, gratis e non pagato: 0. Disdetto conta finché è attivo.
     */
    private static function mensileCents(Tenant $t, string $stato): int
    {
        return match ($stato) {
            'attivo', 'disdetto' => $t->subscription_interval === 'year'
                ? intdiv($t->priceCents('year'), 12)
                : $t->priceCents('month'),
            'esterno' => (int) $t->price_monthly_cents,
            default => 0,
        };
    }

    /** Demo fatte negli ultimi 30 giorni: giorni diversi in cui un account demo è stato usato. */
    private static function giorniDemo(Collection $demoIds): int
    {
        if ($demoIds->isEmpty()) {
            return 0;
        }

        return DB::table('audit_logs')
            ->whereIn('tenant_id', $demoIds)
            ->where('created_at', '>=', now()->subDays(30))
            ->pluck('created_at')
            ->map(fn ($d) => substr((string) $d, 0, 10))
            ->unique()
            ->count();
    }
}
