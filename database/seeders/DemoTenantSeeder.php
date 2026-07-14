<?php

namespace Database\Seeders;

use App\Models\Allegato;
use App\Models\Pratica;
use App\Models\PraticaNota;
use App\Models\Tenant;
use App\Models\TenantStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoTenantSeeder extends Seeder
{
    public const TENANT_NAME  = 'Studio Demo';
    public const ADMIN_EMAIL  = 'demo@kryptodoc.it';
    public const ADMIN_PASS   = 'demo';

    public function run(): void
    {
        // ── Tenant ────────────────────────────────────────────────────
        $tenant = Tenant::firstOrCreate(
            ['name' => self::TENANT_NAME],
            [
                'settings' => [
                    'default_notice_days'  => 14,
                    'custom_fields_schema' => [
                        ['name' => 'numero_sinistro', 'label' => 'N° Sinistro',      'type' => 'text'],
                        ['name' => 'controparte',     'label' => 'Controparte',      'type' => 'text'],
                        ['name' => 'email_cliente',   'label' => 'Email Cliente',    'type' => 'text'],
                        ['name' => 'valore_stimato',  'label' => 'Valore Stimato €', 'type' => 'number'],
                    ],
                ],
            ]
        );

        // ── Stati ─────────────────────────────────────────────────────
        $statNuovo    = TenantStatus::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Nuova Pratica'],
            ['color' => '#6366F1', 'order' => 0, 'is_closed' => false, 'is_initial' => true, 'send_email_notification' => false]);
        $statPerizia  = TenantStatus::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'In Perizia'],
            ['color' => '#F59E0B', 'order' => 1, 'is_closed' => false, 'responsible_role' => 'external', 'send_email_notification' => false]);
        $statAttesa   = TenantStatus::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Attesa Documenti'],
            ['color' => '#F97316', 'order' => 2, 'is_closed' => false, 'send_email_notification' => true]);
        $statChiusa   = TenantStatus::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Chiusa'],
            ['color' => '#10B981', 'order' => 3, 'is_closed' => true,  'send_email_notification' => true]);

        // ── Utenti ────────────────────────────────────────────────────
        $admin = User::firstOrCreate(
            ['email' => self::ADMIN_EMAIL],
            [
                'name'              => 'Admin Demo',
                'email_verified_at' => now(),
                'password'          => Hash::make(self::ADMIN_PASS),
                'role'              => 'tenant-admin',
                'tenant_id'         => $tenant->id,
                'is_active'         => true,
            ]
        );

        $perito = User::firstOrCreate(
            ['email' => 'perito@kryptodoc.it'],
            [
                'name'              => 'Mario Perito',
                'email_verified_at' => now(),
                'password'          => Hash::make('demo'),
                'role'              => 'external',
                'tenant_id'         => $tenant->id,
                'is_active'         => true,
            ]
        );

        // ── Pratiche ──────────────────────────────────────────────────
        $p1 = Pratica::create([
            'tenant_id'            => $tenant->id,
            'utente_creatore_id'   => $admin->id,
            'current_status_id'    => $statNuovo->id,
            'data_prossimo_avviso' => today()->addDays(3)->toDateString(),
            'custom_fields'        => [
                'numero_sinistro' => 'DEMO-2024-001',
                'controparte'     => 'Rossi Mario',
                'email_cliente'   => 'cliente1@example.com',
                'valore_stimato'  => '12500',
            ],
        ]);

        $p2 = Pratica::create([
            'tenant_id'            => $tenant->id,
            'utente_creatore_id'   => $admin->id,
            'current_status_id'    => $statPerizia->id,
            'data_prossimo_avviso' => today()->addDays(7)->toDateString(),
            'custom_fields'        => [
                'numero_sinistro' => 'DEMO-2024-002',
                'controparte'     => 'Bianchi Carla',
                'email_cliente'   => 'cliente2@example.com',
                'valore_stimato'  => '8900',
            ],
        ]);

        $p3 = Pratica::create([
            'tenant_id'            => $tenant->id,
            'utente_creatore_id'   => $admin->id,
            'current_status_id'    => $statAttesa->id,
            'data_prossimo_avviso' => today()->addDays(1)->toDateString(),
            'custom_fields'        => [
                'numero_sinistro' => 'DEMO-2024-003',
                'controparte'     => 'Verdi Luigi',
                'email_cliente'   => 'cliente3@example.com',
                'valore_stimato'  => '34000',
            ],
        ]);

        // ── Note ──────────────────────────────────────────────────────
        PraticaNota::create([
            'pratica_id' => $p1->id,
            'tenant_id'  => $tenant->id,
            'user_id'    => $admin->id,
            'nota'       => 'Pratica aperta. Documentazione iniziale ricevuta via email. In attesa di nomina perito.',
        ]);

        PraticaNota::create([
            'pratica_id' => $p2->id,
            'tenant_id'  => $tenant->id,
            'user_id'    => $admin->id,
            'nota'       => 'Sopralluogo perito fissato per il ' . today()->addDays(5)->format('d/m/Y') . '.',
        ]);

        PraticaNota::create([
            'pratica_id' => $p3->id,
            'tenant_id'  => $tenant->id,
            'user_id'    => $admin->id,
            'nota'       => 'In attesa di: estratto catastale aggiornato e documentazione fotografica danni.',
        ]);

        // ── Allegati (record DB senza file fisico — per demo) ─────────
        Allegato::create([
            'pratica_id' => $p1->id,
            'tenant_id'  => $tenant->id,
            'nome_file'  => 'denuncia_sinistro.pdf',
            's3_key'     => 'demo/denuncia_sinistro.pdf',
        ]);

        Allegato::create([
            'pratica_id' => $p2->id,
            'tenant_id'  => $tenant->id,
            'nome_file'  => 'foto_danno_01.jpg',
            's3_key'     => 'demo/foto_danno_01.jpg',
        ]);

        unset($statChiusa, $perito, $p1, $p2, $p3);
    }
}
