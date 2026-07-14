<?php

namespace Database\Seeders;

use App\Models\Pratica;
use App\Models\PraticaNota;
use App\Models\Tenant;
use App\Models\TenantStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ──────────────────────────────────────────────────────────────
        // 1. SUPERADMIN GLOBALE
        // ──────────────────────────────────────────────────────────────
        User::create([
            'name'              => 'Super Admin',
            'email'             => 'superadmin@kryptodoc.it',
            'email_verified_at' => now(),
            'password'          => Hash::make('password'),
            'role'              => 'superadmin',
            'tenant_id'         => null,
        ]);

        // ──────────────────────────────────────────────────────────────
        // 2. TENANT 1 — Studio Legale Rossi
        // ──────────────────────────────────────────────────────────────
        $rossi = Tenant::create([
            'name'     => 'Studio Legale Rossi',
            'settings' => [
                'default_notice_days'  => 30,
                'custom_fields_schema' => [
                    ['name' => 'numero_sinistro', 'label' => 'Numero Sinistro',    'type' => 'text'],
                    ['name' => 'controparte',     'label' => 'Controparte',        'type' => 'text'],
                    ['name' => 'valore_stimato',  'label' => 'Valore Stimato (€)', 'type' => 'number'],
                ],
            ],
        ]);

        // Stati Rossi
        $rossiAperta  = TenantStatus::create(['tenant_id' => $rossi->id, 'name' => 'Aperta',              'color' => '#3B82F6', 'order' => 0, 'is_closed' => false, 'is_initial' => true]);
        $rossiLavoraz = TenantStatus::create(['tenant_id' => $rossi->id, 'name' => 'In Lavorazione',      'color' => '#F59E0B', 'order' => 1, 'is_closed' => false]);
        $rossiAttesa  = TenantStatus::create(['tenant_id' => $rossi->id, 'name' => 'In Attesa Documenti', 'color' => '#F97316', 'order' => 2, 'is_closed' => false]);
        $rossiChiusa  = TenantStatus::create(['tenant_id' => $rossi->id, 'name' => 'Chiusa',              'color' => '#10B981', 'order' => 3, 'is_closed' => true]);

        // Utenti Rossi
        $rossiAdmin = User::create([
            'name'              => 'Marco Rossi',
            'email'             => 'admin@rossi.it',
            'email_verified_at' => now(),
            'password'          => Hash::make('password'),
            'role'              => 'tenant-admin',
            'tenant_id'         => $rossi->id,
        ]);

        $rossiUser1 = User::create([
            'name'              => 'Giulia Ferrari',
            'email'             => 'user1@rossi.it',
            'email_verified_at' => now(),
            'password'          => Hash::make('password'),
            'role'              => 'user',
            'tenant_id'         => $rossi->id,
        ]);

        $rossiUser2 = User::create([
            'name'              => 'Luca Bianchi',
            'email'             => 'user2@rossi.it',
            'email_verified_at' => now(),
            'password'          => Hash::make('password'),
            'role'              => 'user',
            'tenant_id'         => $rossi->id,
        ]);

        // Pratiche Rossi
        $r1 = Pratica::create([
            'tenant_id'            => $rossi->id,
            'utente_creatore_id'   => $rossiAdmin->id,
            'current_status_id'    => $rossiAperta->id,
            'data_prossimo_avviso' => today()->toDateString(),
            'custom_fields'        => ['numero_sinistro' => 'SIN-2024-001', 'controparte' => 'Mario Verdi',              'valore_stimato' => '15000'],
        ]);

        $r2 = Pratica::create([
            'tenant_id'            => $rossi->id,
            'utente_creatore_id'   => $rossiUser1->id,
            'current_status_id'    => $rossiLavoraz->id,
            'data_prossimo_avviso' => today()->toDateString(),
            'custom_fields'        => ['numero_sinistro' => 'SIN-2024-002', 'controparte' => 'Studio Esposito',         'valore_stimato' => '42500'],
        ]);

        $r3 = Pratica::create([
            'tenant_id'            => $rossi->id,
            'utente_creatore_id'   => $rossiUser1->id,
            'current_status_id'    => $rossiAttesa->id,
            'data_prossimo_avviso' => today()->addDays(3)->toDateString(),
            'custom_fields'        => ['numero_sinistro' => 'SIN-2024-003', 'controparte' => 'Condominio Via Roma 12', 'valore_stimato' => '8200'],
        ]);

        $r4 = Pratica::create([
            'tenant_id'            => $rossi->id,
            'utente_creatore_id'   => $rossiUser2->id,
            'current_status_id'    => $rossiLavoraz->id,
            'data_prossimo_avviso' => today()->addDays(7)->toDateString(),
            'custom_fields'        => ['numero_sinistro' => 'SIN-2024-004', 'controparte' => 'Lucia Neri',              'valore_stimato' => '3700'],
        ]);

        $r5 = Pratica::create([
            'tenant_id'            => $rossi->id,
            'utente_creatore_id'   => $rossiAdmin->id,
            'current_status_id'    => $rossiChiusa->id,
            'data_prossimo_avviso' => today()->subDays(5)->toDateString(),
            'custom_fields'        => ['numero_sinistro' => 'SIN-2023-089', 'controparte' => 'Franco Gentili',          'valore_stimato' => '22000'],
        ]);

        // Note pratiche Rossi
        PraticaNota::create(['pratica_id' => $r1->id, 'tenant_id' => $rossi->id, 'user_id' => $rossiAdmin->id,
            'nota' => 'Pratica aperta a seguito di ricevimento atto di citazione. In attesa di nomina del legale avversario.',
        ]);
        PraticaNota::create(['pratica_id' => $r1->id, 'tenant_id' => $rossi->id, 'user_id' => $rossiUser1->id,
            'nota' => 'Contattato il cliente: confermata disponibilità per udienza del 15/03. Documenti da allegare: contratto e fatture.',
        ]);

        PraticaNota::create(['pratica_id' => $r2->id, 'tenant_id' => $rossi->id, 'user_id' => $rossiUser1->id,
            'nota' => 'Ricevuta memoria difensiva. Depositato controricorso in cancelleria in data odierna.',
        ]);
        PraticaNota::create(['pratica_id' => $r2->id, 'tenant_id' => $rossi->id, 'user_id' => $rossiAdmin->id,
            'nota' => 'Valutazione del danno: perizia tecnica commissionata. Attesi risultati entro 10 giorni lavorativi.',
        ]);

        PraticaNota::create(['pratica_id' => $r3->id, 'tenant_id' => $rossi->id, 'user_id' => $rossiUser1->id,
            'nota' => 'In attesa di: estratto catastale aggiornato, visura camerale e documentazione fotografica danni.',
        ]);

        PraticaNota::create(['pratica_id' => $r4->id, 'tenant_id' => $rossi->id, 'user_id' => $rossiUser2->id,
            'nota' => 'Prima comunicazione inviata alla controparte tramite raccomandata A/R. Attesa risposta entro 30 giorni.',
        ]);
        PraticaNota::create(['pratica_id' => $r4->id, 'tenant_id' => $rossi->id, 'user_id' => $rossiAdmin->id,
            'nota' => 'Sollecito inviato. Fissata mediazione per il 10 del mese prossimo.',
        ]);

        PraticaNota::create(['pratica_id' => $r5->id, 'tenant_id' => $rossi->id, 'user_id' => $rossiAdmin->id,
            'nota' => 'Pratica conclusa con accordo stragiudiziale. Importo liquidato: €19.500. Archiviazione completata.',
        ]);

        // ──────────────────────────────────────────────────────────────
        // 3. TENANT 2 — Assicurazioni Vega
        // ──────────────────────────────────────────────────────────────
        $vega = Tenant::create([
            'name'     => 'Assicurazioni Vega',
            'settings' => [
                'default_notice_days'  => 15,
                'custom_fields_schema' => [
                    ['name' => 'codice_polizza', 'label' => 'Codice Polizza', 'type' => 'text'],
                    ['name' => 'targa_veicolo',  'label' => 'Targa Veicolo',  'type' => 'text'],
                    ['name' => 'data_sinistro',  'label' => 'Data Sinistro',  'type' => 'date'],
                ],
            ],
        ]);

        // Stati Vega
        $vegaNuovo      = TenantStatus::create(['tenant_id' => $vega->id, 'name' => 'Nuovo Sinistro', 'color' => '#6366F1', 'order' => 0, 'is_closed' => false, 'is_initial' => true]);
        $vegaPerizia    = TenantStatus::create(['tenant_id' => $vega->id, 'name' => 'Perizia',        'color' => '#F59E0B', 'order' => 1, 'is_closed' => false]);
        $vegaLiquida    = TenantStatus::create(['tenant_id' => $vega->id, 'name' => 'Liquidazione',   'color' => '#3B82F6', 'order' => 2, 'is_closed' => false]);
        $vegaArchiviato = TenantStatus::create(['tenant_id' => $vega->id, 'name' => 'Archiviato',     'color' => '#10B981', 'order' => 3, 'is_closed' => true]);

        // Utenti Vega
        $vegaAdmin = User::create([
            'name'              => 'Carla Vega',
            'email'             => 'admin@vega.it',
            'email_verified_at' => now(),
            'password'          => Hash::make('password'),
            'role'              => 'tenant-admin',
            'tenant_id'         => $vega->id,
        ]);

        $vegaUser1 = User::create([
            'name'              => 'Roberto Mancini',
            'email'             => 'user1@vega.it',
            'email_verified_at' => now(),
            'password'          => Hash::make('password'),
            'role'              => 'user',
            'tenant_id'         => $vega->id,
        ]);

        // Pratiche Vega
        $v1 = Pratica::create([
            'tenant_id'            => $vega->id,
            'utente_creatore_id'   => $vegaAdmin->id,
            'current_status_id'    => $vegaNuovo->id,
            'data_prossimo_avviso' => today()->toDateString(),
            'custom_fields'        => ['codice_polizza' => 'POL-2024-8821', 'targa_veicolo' => 'AB123CD', 'data_sinistro' => '2024-01-15'],
        ]);

        $v2 = Pratica::create([
            'tenant_id'            => $vega->id,
            'utente_creatore_id'   => $vegaUser1->id,
            'current_status_id'    => $vegaPerizia->id,
            'data_prossimo_avviso' => today()->addDays(5)->toDateString(),
            'custom_fields'        => ['codice_polizza' => 'POL-2024-9103', 'targa_veicolo' => 'EF456GH', 'data_sinistro' => '2024-02-03'],
        ]);

        $v3 = Pratica::create([
            'tenant_id'            => $vega->id,
            'utente_creatore_id'   => $vegaAdmin->id,
            'current_status_id'    => $vegaArchiviato->id,
            'data_prossimo_avviso' => today()->subDays(10)->toDateString(),
            'custom_fields'        => ['codice_polizza' => 'POL-2023-7755', 'targa_veicolo' => 'GH789IJ', 'data_sinistro' => '2023-11-20'],
        ]);

        // Note pratiche Vega
        PraticaNota::create(['pratica_id' => $v1->id, 'tenant_id' => $vega->id, 'user_id' => $vegaAdmin->id,
            'nota' => 'Sinistro registrato. Sopralluogo perito programmato per il 22/01. Contattato assicurato via email.',
        ]);

        PraticaNota::create(['pratica_id' => $v2->id, 'tenant_id' => $vega->id, 'user_id' => $vegaUser1->id,
            'nota' => 'Perizia tecnica in corso. Danno preliminarmente stimato in €4.200. In attesa relazione finale perito.',
        ]);
        PraticaNota::create(['pratica_id' => $v2->id, 'tenant_id' => $vega->id, 'user_id' => $vegaAdmin->id,
            'nota' => 'Relazione peritale ricevuta e validata. Avviata procedura di liquidazione. Contattata controparte assicurativa.',
        ]);

        PraticaNota::create(['pratica_id' => $v3->id, 'tenant_id' => $vega->id, 'user_id' => $vegaAdmin->id,
            'nota' => 'Pratica archiviata. Liquidazione completata per €3.800. Nessuna ulteriore azione richiesta.',
        ]);

        // Supressa l'avvertenza IDE: $rossiChiusa e $vegaLiquida sono create ma
        // non referenziate dopo — usate in pratiche via status_id risolti sopra.
        unset($rossiChiusa, $vegaLiquida);
    }
}
