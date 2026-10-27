<?php

namespace App\Console\Commands;

use App\Models\Automation;
use App\Models\Cliente;
use App\Models\Contatto;
use App\Models\Ispezione;
use App\Models\ListaValori;
use App\Models\Pratica;
use App\Models\PraticaNota;
use App\Models\Tenant;
use App\Models\TenantStatus;
use App\Models\User;
use App\Models\Veicolo;
use App\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Riempie un account dimostrativo con clienti, veicoli, periti e sinistri finti da mostrare nelle demo.
 *
 * Sicurezza: lavora su un solo tenant, indicato per id E per nome (devono coincidere), e solo se è
 * vuoto (nessun cliente e nessun sinistro). Ogni record viene creato con il tenant_id esplicito.
 * Il tenant viene marcato settings.demo = true: da lì non parte nessuna email (TenantMailerResolver).
 *
 *   php artisan demo:popola 6 --nome=demo
 */
class PopolaDemo extends Command
{
    protected $signature = 'demo:popola {tenant : Id del tenant demo} {--nome= : Nome esatto del tenant, per conferma}
                            {--automazioni : Aggiunge solo le automazioni sui cambi di stato a un tenant demo già popolato}';

    protected $description = 'Crea dati finti (clienti, veicoli, sinistri, note) in un account demo vuoto';

    private const CLIENTI = [
        // nome, telefono, città, provincia, privato?
        ['ROSSI MARIO', '3401234501', 'Milano', 'MI', true],
        ['BIANCHI LAURA', '3471234502', 'Monza', 'MB', true],
        ['FERRARI GIUSEPPE', '3331234503', 'Bergamo', 'BG', true],
        ['ESPOSITO ANNA', '3391234504', 'Como', 'CO', true],
        ['ROMANO LUCA', '3201234505', 'Varese', 'VA', true],
        ['COLOMBO FRANCESCA', '3451234506', 'Lecco', 'LC', true],
        ['RICCI ANDREA', '3281234507', 'Pavia', 'PV', true],
        ['MARINO GIULIA', '3491234508', 'Lodi', 'LO', true],
        ['GRECO PAOLO', '3381234509', 'Brescia', 'BS', true],
        ['BRUNO ELENA', '3471234510', 'Cremona', 'CR', true],
        ['GALLO STEFANO', '3401234511', 'Milano', 'MI', true],
        ['CONTI MARTA', '3331234512', 'Novara', 'NO', true],
        ['TRASPORTI LOMBARDI SRL', '0291234513', 'Sesto San Giovanni', 'MI', false],
        ['EDILNORD SNC', '0351234514', 'Treviglio', 'BG', false],
        ['FARMACIA SAN ROCCO SAS', '0311234515', 'Cantù', 'CO', false],
    ];

    private const VEICOLI = [
        ['Fiat', 'Panda'], ['Volkswagen', 'Golf'], ['Toyota', 'Yaris'], ['Renault', 'Clio'],
        ['Peugeot', '208'], ['Dacia', 'Duster'], ['Audi', 'A3'], ['Ford', 'Puma'],
        ['Jeep', 'Renegade'], ['BMW', 'Serie 1'], ['Iveco', 'Daily'], ['Fiat', 'Ducato'],
    ];

    private const COMPAGNIE = ['Generali', 'Allianz', 'UnipolSai', 'AXA', 'Reale Mutua', 'Zurich', 'Cattolica'];

    // [indice cliente, indice veicolo del cliente o null, compagnia, stato (indice), giorni al prossimo avviso, giorni da apertura, note]
    private const SINISTRI = [
        [0, 0, 'Generali', 0, 2, 3, ['Denuncia ricevuta via WhatsApp con foto del danno al paraurti posteriore.', 'Inviato modulo CAI al cliente da firmare.']],
        [1, 0, 'Allianz', 1, -2, 18, ['Perito incaricato, sopralluogo in carrozzeria fissato.', 'Il perito chiede preventivo dettagliato della carrozzeria.']],
        [2, 0, 'UnipolSai', 2, 0, 25, ['Mancano: copia patente del conducente e verbale della Polizia Locale.', 'Sollecitato il cliente al telefono, porta i documenti entro venerdì.']],
        [3, null, 'Reale Mutua', 1, 5, 12, ['Sinistro casa: infiltrazione d\'acqua dal piano superiore, danni al soffitto della cucina.', 'Sopralluogo del perito concordato con l\'amministratore di condominio.']],
        [4, 0, 'AXA', 3, 7, 40, ['Perizia chiusa: danno riconosciuto 2.350 €.', 'In attesa del bonifico della compagnia.']],
        [5, 0, 'Generali', 4, null, 75, ['Liquidati 1.870 €. Cliente soddisfatto, pratica chiusa.']],
        [6, null, 'Zurich', 2, -1, 30, ['Infortunio in bicicletta: frattura al polso, 30 giorni di prognosi.', 'Servono certificato di chiusura infortunio e fatture delle visite.']],
        [7, 0, 'Allianz', 0, 1, 1, ['Tamponamento in coda in tangenziale, ragione al 100%.']],
        [8, 0, 'Cattolica', 1, 3, 9, ['Grandine: danni a cofano e tetto.', 'Perito disponibile la prossima settimana.']],
        [9, null, 'UnipolSai', 3, 10, 50, ['RC capofamiglia: il figlio ha rotto la vetrina di un negozio.', 'Accordo con il negoziante, in attesa quietanza firmata.']],
        [10, 0, 'AXA', 2, -4, 22, ['Furto parziale: rubati i cerchi. Denuncia ai Carabinieri ricevuta.', 'Manca la fattura d\'acquisto dei cerchi.']],
        [11, 0, 'Reale Mutua', 4, null, 90, ['Cristalli: parabrezza sostituito in convenzione, nessun costo per il cliente.']],
        [12, 0, 'Generali', 1, 4, 14, ['Furgone aziendale urtato in retromarcia da un camion durante lo scarico.', 'Perito incaricato. Il cliente chiede l\'auto sostitutiva.']],
        [12, 1, 'Generali', 0, 6, 2, ['Secondo mezzo della flotta: specchietto rotto in parcheggio, controparte ignota.']],
        [13, null, 'Allianz', 2, 2, 35, ['Danno a terzi in cantiere: caduta di materiale su un\'auto in sosta.', 'Richiesta alla controparte la foto della targa e il preventivo.']],
        [14, null, 'Zurich', 3, 8, 45, ['Allagamento del magazzino della farmacia dopo il temporale.', 'Perizia conclusa, stima del danno 6.400 €. Attesa offerta della compagnia.']],
        [0, 0, 'Generali', 4, null, 160, ['Sinistro dell\'anno scorso: liquidato e chiuso.']],
        [2, 0, 'UnipolSai', 0, 3, 0, ['Aperto oggi: urto in rotonda, controparte con constatazione amichevole firmata.']],
    ];

    private const STATI = [
        ['Aperto', '#6366F1', false, true],
        ['In perizia', '#F59E0B', false, false],
        ['Attesa documenti', '#F97316', false, false],
        ['In liquidazione', '#3B82F6', false, false],
        ['Chiuso', '#10B981', true, false],
    ];

    public function handle(): int
    {
        $tenant = Tenant::find((int) $this->argument('tenant'));

        if (! $tenant) {
            $this->error('Tenant non trovato.');

            return self::FAILURE;
        }

        if ($this->option('nome') === null || $tenant->name !== $this->option('nome')) {
            $this->error("Il tenant #{$tenant->id} si chiama \"{$tenant->name}\": passa --nome=\"{$tenant->name}\" per confermare.");

            return self::FAILURE;
        }

        if ($this->option('automazioni')) {
            return $this->soloAutomazioni($tenant);
        }

        $clienti = Cliente::forTenant($tenant->id)->count();
        $sinistri = Pratica::forTenant($tenant->id)->count();
        if ($clienti > 0 || $sinistri > 0) {
            $this->error("Il tenant #{$tenant->id} non è vuoto ({$clienti} clienti, {$sinistri} sinistri): non tocco niente.");

            return self::FAILURE;
        }

        $admin = User::where('tenant_id', $tenant->id)->where('role', 'tenant-admin')->orderBy('id')->first();
        if (! $admin) {
            $this->error("Il tenant #{$tenant->id} non ha un amministratore.");

            return self::FAILURE;
        }

        TenantContext::set($tenant->id);

        DB::transaction(function () use ($tenant, $admin) {
            $settings = $tenant->settings ?? [];
            $settings['demo'] = true;
            $settings['features'] = array_merge($settings['features'] ?? [], ['clienti' => true, 'kanban' => true]);
            $tenant->update(['settings' => $settings]);

            $stati = $this->stati($tenant);

            ListaValori::forTenant($tenant->id)->where('slug', 'compagnie')->exists()
                || ListaValori::create(['tenant_id' => $tenant->id, 'nome' => 'Compagnie', 'slug' => 'compagnie', 'items' => self::COMPAGNIE]);

            $periti = collect([
                ['Ing. Marco Valli', '3351112201'],
                ['Studio Peritale Sala', '0291112202'],
            ])->map(fn ($p) => Contatto::create([
                'tenant_id' => $tenant->id, 'nome' => $p[0], 'tags' => [Contatto::TAG_PERITO],
                'telefono' => $p[1], 'email' => strtolower(str_replace([' ', '.'], '', $p[0])) . '@example.com', 'is_active' => true,
            ]));
            Contatto::create([
                'tenant_id' => $tenant->id, 'nome' => 'Carrozzeria Rapida', 'tags' => [Contatto::TAG_CARROZZERIA],
                'telefono' => '0291112203', 'email' => 'carrozzeriarapida@example.com', 'is_active' => true,
            ]);

            $clienti = [];
            $veicoli = [];
            foreach (self::CLIENTI as $i => [$nome, $telefono, $citta, $provincia, $privato]) {
                $clienti[$i] = Cliente::create([
                    'tenant_id'      => $tenant->id,
                    'codice_esterno' => sprintf('DEMO-%03d', $i + 1),
                    'nome'           => $nome,
                    'telefono'       => $telefono,
                    'email'          => strtolower(str_replace(' ', '.', $nome)) . '@example.com',
                    'codice_fiscale' => $privato ? $this->codiceFiscaleFinto($i) : null,
                    'partita_iva'    => $privato ? null : sprintf('0%010d', 1234567 + $i),
                    'indirizzo'      => 'Via Roma ' . ($i + 3),
                    'cap'            => '20' . str_pad((string) (100 + $i), 3, '0', STR_PAD_LEFT),
                    'citta'          => $citta,
                    'provincia'      => $provincia,
                ]);

                // Le aziende hanno due mezzi, i privati quasi tutti uno; tre clienti nessuno (sinistri casa/infortuni/RC).
                $quanti = $privato ? (in_array($i, [3, 6, 9], true) ? 0 : 1) : ($i === 14 ? 0 : 2);
                for ($k = 0; $k < $quanti; $k++) {
                    [$marca, $modello] = $privato ? self::VEICOLI[$i % 10] : self::VEICOLI[10 + $k];
                    $veicoli[$i][$k] = Veicolo::create([
                        'tenant_id' => $tenant->id,
                        'cliente_id' => $clienti[$i]->id,
                        'targa' => $this->targa($i * 2 + $k),
                        'marca' => $marca,
                        'modello' => $modello,
                        'data_immatricolazione' => today()->subYears(2 + ($i % 8))->subMonths($i)->toDateString(),
                    ]);
                }
            }

            $this->automazioni($tenant, $stati);

            foreach (self::SINISTRI as $n => [$c, $v, $compagnia, $stato, $avviso, $giorniFa, $note]) {
                $apertura = now()->subDays($giorniFa)->setTime(9 + ($n % 8), 15);
                $pratica = Pratica::create([
                    'tenant_id' => $tenant->id,
                    'utente_creatore_id' => $admin->id,
                    'cliente_id' => $clienti[$c]->id,
                    'veicolo_id' => $v === null ? null : $veicoli[$c][$v]->id,
                    'compagnia' => $compagnia,
                    'current_status_id' => $stati[$stato]->id,
                    // Chiusi: nessun prossimo avviso.
                    'data_prossimo_avviso' => $avviso === null ? null : today()->addDays($avviso)->toDateString(),
                ]);
                $pratica->forceFill(['created_at' => $apertura, 'updated_at' => $apertura])->saveQuietly();

                Ispezione::create([
                    'tenant_id' => $tenant->id,
                    'pratica_id' => $pratica->id,
                    'perito_contatto_id' => $stato >= 1 ? $periti[$n % 2]->id : null,
                    'stato' => 'pianificata',
                    'data_appuntamento' => $stato === 1 ? today()->addDays(2 + $n % 4)->setTime(10, 0) : null,
                ]);

                foreach ($note as $k => $testo) {
                    $nota = PraticaNota::create([
                        'pratica_id' => $pratica->id,
                        'tenant_id' => $tenant->id,
                        'user_id' => $admin->id,
                        'nota' => $testo,
                    ]);
                    $quando = $apertura->copy()->addDays((int) floor($giorniFa * $k / max(count($note), 1)))->addMinutes(30);
                    $nota->forceFill(['created_at' => $quando, 'updated_at' => $quando])->saveQuietly();
                }
            }
        });

        TenantContext::clear();

        $this->info(sprintf('Tenant #%d "%s": %d clienti, %d sinistri, periti e note creati. Email disattivate (account demo).',
            $tenant->id, $tenant->name, count(self::CLIENTI), count(self::SINISTRI)));

        return self::SUCCESS;
    }

    /** Solo su un tenant già marcato demo (quindi senza email vere) e che non ha ancora automazioni. */
    private function soloAutomazioni(Tenant $tenant): int
    {
        if (! $tenant->getSetting('demo')) {
            $this->error("Il tenant #{$tenant->id} non è un account demo: niente automazioni (manderebbero messaggi veri).");

            return self::FAILURE;
        }

        if (Automation::where('tenant_id', $tenant->id)->exists()) {
            $this->error("Il tenant #{$tenant->id} ha già delle automazioni: non tocco niente.");

            return self::FAILURE;
        }

        $stati = TenantStatus::where('tenant_id', $tenant->id)->orderBy('order')->get();
        $aperti = $stati->where('is_closed', false)->values();
        if ($aperti->count() < 2 || ! $stati->contains('is_closed', true)) {
            $this->error('Servono almeno due stati aperti e uno chiuso.');

            return self::FAILURE;
        }

        $creati = $this->automazioni($tenant, [
            $aperti[0], $aperti[1], $aperti[min(2, $aperti->count() - 1)], $aperti[$aperti->count() - 1], $stati->firstWhere('is_closed', true),
        ]);
        $this->info("Tenant #{$tenant->id} \"{$tenant->name}\": {$creati} automazioni sui cambi di stato (chiedono conferma; email non inviate, account demo).");

        return self::SUCCESS;
    }

    /**
     * Un messaggio al cliente (e al perito quando entra in perizia) a ogni cambio di stato: spostando
     * una scheda sulla Board compare la conferma con testo e destinatari modificabili.
     *
     * @param  TenantStatus[]  $stati  [aperto, perizia, attesa documenti, liquidazione, chiuso]
     */
    private function automazioni(Tenant $tenant, array $stati): int
    {
        $regole = [
            [1, 'Avviso perizia a cliente e perito', [['type' => 'cliente'], ['type' => 'perito']],
                "Gentile {nome_cliente}, per il sinistro n. {numero_pratica} abbiamo incaricato il perito, che la contatterà a breve per fissare il sopralluogo.\n\n{nome_tenant}"],
            [2, 'Richiesta documenti al cliente', [['type' => 'cliente']],
                "Gentile {nome_cliente}, per proseguire con il sinistro n. {numero_pratica} ci servono alcuni documenti (patente, libretto, eventuale verbale). Può inviarceli rispondendo a questa email o passando in agenzia.\n\n{nome_tenant}"],
            [3, 'Sinistro in liquidazione', [['type' => 'cliente']],
                "Gentile {nome_cliente}, buone notizie: il sinistro n. {numero_pratica} è in liquidazione. Le scriviamo appena la compagnia conferma il pagamento.\n\n{nome_tenant}"],
            [4, 'Sinistro chiuso', [['type' => 'cliente']],
                "Gentile {nome_cliente}, il sinistro n. {numero_pratica} è chiuso. Grazie per la fiducia: per qualsiasi cosa siamo a disposizione.\n\n{nome_tenant}"],
        ];

        foreach ($regole as [$indice, $nome, $destinatari, $testo]) {
            Automation::create([
                'tenant_id' => $tenant->id, 'name' => $nome, 'trigger_type' => 'status', 'tenant_status_id' => $stati[$indice]->id,
                'channel' => 'email', 'recipients_to' => $destinatari, 'message_template' => $testo,
                'is_active' => true, 'requires_confirmation' => true,
            ]);
        }

        return count($regole);
    }

    /** Usa gli stati già configurati del tenant se ci sono, altrimenti crea quelli standard. */
    private function stati(Tenant $tenant): array
    {
        $esistenti = TenantStatus::where('tenant_id', $tenant->id)->orderBy('order')->get();

        if ($esistenti->count() >= 3 && $esistenti->contains('is_closed', true)) {
            $aperti = $esistenti->where('is_closed', false)->values();
            $chiuso = $esistenti->firstWhere('is_closed', true);

            // Ripartisce i 4 stati "aperti" dei dati demo sugli stati aperti esistenti.
            return [
                $aperti[0],
                $aperti[min(1, $aperti->count() - 1)],
                $aperti[min(2, $aperti->count() - 1)],
                $aperti[$aperti->count() - 1],
                $chiuso,
            ];
        }

        TenantStatus::where('tenant_id', $tenant->id)->delete();

        return collect(self::STATI)->map(fn ($s, $i) => TenantStatus::create([
            'tenant_id' => $tenant->id, 'name' => $s[0], 'color' => $s[1], 'order' => $i,
            'is_closed' => $s[2], 'is_initial' => $s[3], 'send_email_notification' => false,
        ]))->all();
    }

    private function targa(int $n): string
    {
        $lettere = 'ABCDEFGHJKLMNPRSTVWXYZ';

        return 'G' . $lettere[$n % 22] . sprintf('%03d', 100 + $n * 37 % 900) . $lettere[($n * 7) % 22] . $lettere[($n * 3 + 5) % 22];
    }

    private function codiceFiscaleFinto(int $n): string
    {
        // Formato plausibile ma inventato: non corrisponde a nessuna persona.
        return strtoupper(sprintf('DMO%sXX%02dA01F205%s', chr(65 + $n), 80 + $n % 15, chr(65 + ($n * 3) % 26)));
    }
}
