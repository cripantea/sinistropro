<?php

namespace App\Services\ImportClienti;

use App\Models\Automation;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Importa clienti (con i loro veicoli) da righe già lette dal file.
 *
 * Regole:
 * - non si cancella mai niente: un cliente che sparisce dal file resta;
 * - si scrive solo ciò che serve: clienti/veicoli nuovi, campi vuoti da riempire e
 *   campi cambiati nel gestionale che nessuno ha corretto a mano in SinistroPro
 *   (si confronta con i valori dell'import precedente, salvati in `dati_import`);
 * - più righe con lo stesso codice cliente sono lo stesso cliente (export "una riga per veicolo");
 * - un cliente già presente si riconosce dal codice del gestionale; quelli inseriti a mano
 *   (senza codice) vengono abbinati uno per uno (vedi riconcilia()) e l'utente può correggere.
 */
class ClientiImporter
{
    public const CLIENTE_FIELDS = [
        'codice_esterno' => 'Codice cliente (gestionale)',
        'nome'           => 'Nome / Ragione sociale',
        'telefono'       => 'Telefono',
        'email'          => 'Email',
        'codice_fiscale' => 'Codice fiscale',
        'partita_iva'    => 'Partita IVA',
        'indirizzo'      => 'Indirizzo',
        'cap'            => 'CAP',
        'citta'          => 'Città',
        'provincia'      => 'Provincia',
    ];

    public const VEICOLO_FIELDS = [
        'targa'                 => 'Targa',
        'telaio'                => 'Telaio',
        'marca'                 => 'Marca',
        'modello'               => 'Modello',
        'descrizione'           => 'Descrizione',
        'data_immatricolazione' => 'Data immatricolazione',
        'scadenza_revisione'    => 'Scadenza revisione',
        'km'                    => 'Km',
        'ultima_visita'         => 'Ultima visita',
    ];

    private const VEICOLO_DATES = ['data_immatricolazione', 'scadenza_revisione', 'ultima_visita'];

    /** Più colonne verso questi campi vengono unite (es. "Cognome" + "Nome"). */
    private const CONCAT_FIELDS = ['nome', 'indirizzo'];

    /** Valori che nei gestionali significano "vuoto". */
    private const PLACEHOLDERS = ['.', '-', '--', '/', 'nomail', 'no mail', 'n/a', 'n.d.', 'nd', 'null', 'none', '0000-00-00'];

    /** Intestazione normalizzata → campo, per proporre la mappatura al primo import. */
    private const SYNONYMS = [
        'codcliente' => 'codice_esterno', 'codicecliente' => 'codice_esterno', 'idcliente' => 'codice_esterno',
        'codice' => 'codice_esterno', 'cod' => 'codice_esterno', 'clientecodice' => 'codice_esterno',
        'nome' => 'nome', 'cognome' => 'nome', 'cognomenome' => 'nome', 'nominativo' => 'nome',
        'ragionesociale' => 'nome', 'denominazione' => 'nome', 'cliente' => 'nome', 'name' => 'nome',
        'telefono' => 'telefono', 'tel' => 'telefono', 'telcellulare' => 'telefono', 'cellulare' => 'telefono',
        'cell' => 'telefono', 'mobile' => 'telefono', 'phone' => 'telefono',
        'email' => 'email', 'mail' => 'email', 'indirizzoemail' => 'email',
        'codicefiscale' => 'codice_fiscale', 'cf' => 'codice_fiscale', 'codfiscale' => 'codice_fiscale',
        'partitaiva' => 'partita_iva', 'piva' => 'partita_iva',
        'indirizzo' => 'indirizzo', 'via' => 'indirizzo',
        'cap' => 'cap',
        'citta' => 'citta', 'comune' => 'citta', 'localita' => 'citta',
        'provincia' => 'provincia', 'prov' => 'provincia',
        'targa' => 'veicolo.targa',
        'telaio' => 'veicolo.telaio', 'vin' => 'veicolo.telaio', 'numerotelaio' => 'veicolo.telaio',
        'marca' => 'veicolo.marca',
        'modello' => 'veicolo.modello',
        'descrizione' => 'veicolo.descrizione', 'versione' => 'veicolo.descrizione',
        'dataimm' => 'veicolo.data_immatricolazione', 'dataimmatricolazione' => 'veicolo.data_immatricolazione',
        'immatricolazione' => 'veicolo.data_immatricolazione',
        'datascadenzarevisione' => 'veicolo.scadenza_revisione', 'scadenzarevisione' => 'veicolo.scadenza_revisione',
        'km' => 'veicolo.km', 'chilometri' => 'veicolo.km',
        'ultimavisita' => 'veicolo.ultima_visita',
    ];

    private const MAX_RIGHE_AVVISO = 20;

    private array $avvisi = [];

    /**
     * Campi di destinazione proposti nella mappatura.
     *
     * @return array<int, array{value: string, label: string, group: string}>
     */
    public static function targets(array $schema): array
    {
        $out = [];
        foreach (self::CLIENTE_FIELDS as $k => $label) {
            $out[] = ['value' => $k, 'label' => $label, 'group' => 'Cliente'];
        }
        foreach (self::VEICOLO_FIELDS as $k => $label) {
            $out[] = ['value' => "veicolo.{$k}", 'label' => $label, 'group' => 'Veicolo'];
        }
        foreach ($schema as $f) {
            $out[] = ['value' => "custom_fields.{$f['name']}", 'label' => $f['label'], 'group' => 'Campi personalizzati'];
        }

        return $out;
    }

    /** Mappatura proposta: quella salvata dall'import precedente, poi i sinonimi noti. */
    public static function suggestMapping(array $headers, array $schema, array $saved): array
    {
        $valid = array_column(self::targets($schema), 'value');
        $known = self::SYNONYMS;
        foreach ($schema as $f) {
            $known[self::normalizeHeader($f['name'])] = "custom_fields.{$f['name']}";
            $known[self::normalizeHeader($f['label'])] = "custom_fields.{$f['name']}";
        }

        $mapping = [];
        $used = [];
        foreach ($headers as $h) {
            // "veicoli.targa", "contatti.email": se il nome completo non dice nulla conta l'ultima parte.
            $last = str_contains($h, '.') ? self::normalizeHeader(substr(strrchr($h, '.'), 1)) : null;
            $target = $saved[$h] ?? $known[self::normalizeHeader($h)] ?? ($last ? $known[$last] ?? '' : '');
            if (! in_array($target, $valid, true)) {
                $target = '';
            }
            // Senza mappatura salvata, un campo va a una sola colonna (salvo quelli concatenabili).
            if ($target !== '' && ! isset($saved[$h]) && isset($used[$target]) && ! in_array($target, self::CONCAT_FIELDS, true)) {
                $target = '';
            }
            $mapping[$h] = $target;
            $used[$target] = true;
        }

        return $mapping;
    }

    private static function normalizeHeader(string $h): string
    {
        $h = mb_strtolower($h);
        $h = strtr($h, ['à' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u']);

        return preg_replace('/[^a-z0-9]/', '', $h);
    }

    /**
     * Esegue (o simula, con $dryRun) l'import.
     *
     * Per ogni cliente e veicolo del file si confronta campo per campo con quello che c'è già:
     * si scrive solo quando qualcosa è davvero da aggiungere o da aggiornare.
     *
     * @param  array<int, array<string, string>>  $rows
     * @param  array<string, string>  $mapping  intestazione → campo
     * @param  array{aggiorna_esistenti?: bool, abbinamenti?: array<int|string, ?string>}  $options
     *         abbinamenti: id cliente già presente → codice del file a cui abbinarlo ('' = non abbinare)
     * @return array{riepilogo: array<string, mixed>, avvisi: array<int, array<string, mixed>>, riconciliazione: array<int, array<string, mixed>>}
     */
    public function process(int $tenantId, array $schema, array $rows, array $mapping, array $options, bool $dryRun, ?callable $progress = null): array
    {
        $this->avvisi = [];
        $aggiorna = (bool) ($options['aggiorna_esistenti'] ?? true);
        $types = collect($schema)->pluck('type', 'name')->all();
        $mapping = array_filter($mapping, fn ($t) => is_string($t) && $t !== '');

        $groups = $this->group($rows, $mapping, $types);

        $r = [
            'righe' => count($rows), 'clienti_file' => count($groups),
            'clienti_nuovi' => 0, 'clienti_esistenti' => 0, 'clienti_aggiornati' => 0, 'clienti_abbinati' => 0,
            'veicoli_nuovi' => 0, 'veicoli_esistenti' => 0, 'veicoli_aggiornati' => 0, 'veicoli_spostati' => 0, 'scartati' => 0,
            'esempi_nuovi' => [], 'esempi_modifiche' => [],
        ];

        // ── Stato attuale del tenant ─────────────────────────────────────────
        $existing = DB::table('clienti')->where('tenant_id', $tenantId)
            ->get(['id', ...array_keys(self::CLIENTE_FIELDS), 'custom_fields', 'dati_import'])
            ->keyBy('id');

        $byCodice = [];
        foreach ($existing as $c) {
            if ($c->codice_esterno !== null && $c->codice_esterno !== '') {
                $byCodice[$c->codice_esterno] = $c->id;
            }
        }

        // Clienti inseriti a mano (senza codice): abbinamento uno per uno con il file.
        [$abbinati, $riconciliazione] = $this->riconcilia(
            $tenantId,
            $existing->filter(fn ($c) => $c->codice_esterno === null || $c->codice_esterno === ''),
            $groups,
            $byCodice,
            $options['abbinamenti'] ?? [],
        );

        $veicoliByTelaio = [];
        $veicoliByTarga = [];
        foreach (DB::table('veicoli')->where('tenant_id', $tenantId)->get(['id', 'cliente_id', ...array_keys(self::VEICOLO_FIELDS), 'dati_import']) as $v) {
            if ($v->telaio) {
                $veicoliByTelaio[$v->telaio] = $v;
            }
            if ($v->targa) {
                $veicoliByTarga["{$v->cliente_id}|{$v->targa}"] = $v;
            }
        }

        $now = now();
        $done = 0;

        foreach (array_chunk($groups, 500, true) as $chunk) {
            $work = function () use ($chunk, $tenantId, $aggiorna, $dryRun, $now, $existing, $byCodice, $abbinati, &$veicoliByTelaio, &$veicoliByTarga, &$r): void {
                foreach ($chunk as $gi => $g) {
                    $c = $g['cliente'];

                    if (empty($c['nome'])) {
                        $r['scartati']++;
                        $this->avviso('senza_nome', 'Righe senza nome: non importate', $g['riga']);
                        continue;
                    }

                    $id = ! empty($c['codice_esterno']) && isset($byCodice[$c['codice_esterno']])
                        ? $byCodice[$c['codice_esterno']]
                        : ($abbinati[$gi] ?? null);

                    if ($id !== null) {
                        $r['clienti_esistenti']++;
                        $current = $existing[$id];
                        $snapshot = json_decode($current->dati_import ?? 'null', true);
                        $updates = [];

                        if (isset($abbinati[$gi])) {
                            $r['clienti_abbinati']++;
                            if (! empty($c['codice_esterno'])) {
                                $updates['codice_esterno'] = $c['codice_esterno'];
                            }
                        }

                        $modifiche = [];
                        if ($aggiorna) {
                            $fields = array_diff_key($c, ['codice_esterno' => 1]);
                            $modifiche = $this->diff($fields, (array) $current, $snapshot);
                            $customCurrent = json_decode($current->custom_fields ?? 'null', true) ?: [];
                            $customDiff = $this->diff($g['custom'], $customCurrent, $snapshot === null ? null : ($snapshot['custom'] ?? []));
                            if ($customDiff) {
                                $updates['custom_fields'] = json_encode(array_merge($customCurrent, array_map(fn ($m) => $m['nuovo'], $customDiff)), JSON_UNESCAPED_UNICODE);
                                $modifiche += $customDiff;
                            }
                            foreach (array_diff_key($modifiche, $customDiff) as $k => $m) {
                                $updates[$k] = $m['nuovo'];
                            }
                        }

                        if ($modifiche) {
                            $r['clienti_aggiornati']++;
                            $this->esempioModifica($r, $current->nome, $modifiche);
                        }

                        $newSnapshot = $this->snapshot($c, $g['custom']);
                        if ($newSnapshot !== $snapshot) {
                            $updates['dati_import'] = json_encode($newSnapshot, JSON_UNESCAPED_UNICODE);
                        }
                        if ($updates && ! $dryRun) {
                            DB::table('clienti')->where('id', $id)->update($updates + ['updated_at' => $now]);
                        }
                    } else {
                        $r['clienti_nuovi']++;
                        if (count($r['esempi_nuovi']) < 10) {
                            $r['esempi_nuovi'][] = $c['nome'];
                        }
                        if (! $dryRun) {
                            $id = DB::table('clienti')->insertGetId($c + [
                                'tenant_id'     => $tenantId,
                                'custom_fields' => $g['custom'] ? json_encode($g['custom'], JSON_UNESCAPED_UNICODE) : null,
                                'dati_import'   => json_encode($this->snapshot($c, $g['custom']), JSON_UNESCAPED_UNICODE),
                                'created_at'    => $now,
                                'updated_at'    => $now,
                            ]);
                        }
                    }

                    $this->veicoli($g, $id, $tenantId, $aggiorna, $dryRun, $now, $veicoliByTelaio, $veicoliByTarga, $r);
                }
            };

            $dryRun ? $work() : DB::transaction($work);

            $done += count($chunk);
            if ($progress) {
                $progress($done, count($groups));
            }
        }

        $this->avvisiAutomazioni($tenantId, $mapping);

        return ['riepilogo' => $r, 'avvisi' => array_values($this->avvisi), 'riconciliazione' => $riconciliazione];
    }

    /** Veicoli di un cliente del file: nuovi si aggiungono, quelli presenti si aggiornano se cambiati. */
    private function veicoli(array $g, ?int $clienteId, int $tenantId, bool $aggiorna, bool $dryRun, $now, array &$byTelaio, array &$byTarga, array &$r): void
    {
        $nuovi = [];

        foreach ($g['veicoli'] as $v) {
            $current = $v['telaio']
                ? ($byTelaio[$v['telaio']] ?? null)
                : ($clienteId !== null ? ($byTarga["{$clienteId}|{$v['targa']}"] ?? null) : null);

            if ($current === null) {
                $r['veicoli_nuovi']++;
                $row = $v + ['tenant_id' => $tenantId, 'cliente_id' => $clienteId, 'dati_import' => json_encode($this->snapshot($v), JSON_UNESCAPED_UNICODE), 'created_at' => $now, 'updated_at' => $now];
                $placeholder = (object) ($row + ['id' => null]);
                if ($v['telaio']) {
                    $byTelaio[$v['telaio']] = $placeholder;
                }
                if ($clienteId !== null && $v['targa']) {
                    $byTarga["{$clienteId}|{$v['targa']}"] = $placeholder;
                }
                $nuovi[] = $row;
                continue;
            }

            $r['veicoli_esistenti']++;

            // Stesso telaio su un altro cliente: passaggio di proprietà, l'auto segue il nuovo proprietario.
            // I sinistri già aperti restano al cliente di allora.
            $spostato = $clienteId === null || (int) $current->cliente_id !== $clienteId;
            if ($spostato) {
                $r['veicoli_spostati']++;
                $this->avviso('veicolo_spostato', 'Veicoli passati a un altro proprietario: spostati sul nuovo cliente', $g['riga']);
                if ($current->id === null) {
                    continue;
                }
            } elseif (! $aggiorna || $current->id === null) {
                continue;
            }

            $snapshot = json_decode($current->dati_import ?? 'null', true);
            $modifiche = $aggiorna ? $this->diff($v, (array) $current, $snapshot) : [];
            $updates = array_map(fn ($m) => $m['nuovo'], $modifiche);
            if ($modifiche && ! $spostato) {
                $r['veicoli_aggiornati']++;
            }
            if ($spostato) {
                // Nel dry run il cliente nuovo non ha ancora un id: si conta e basta.
                if ($clienteId === null) {
                    continue;
                }
                $updates['cliente_id'] = $clienteId;
                $current->cliente_id = $clienteId;
                if ($v['targa']) {
                    $byTarga["{$clienteId}|{$v['targa']}"] = $current;
                }
            }
            $newSnapshot = $this->snapshot($v);
            if ($newSnapshot !== $snapshot) {
                $updates['dati_import'] = json_encode($newSnapshot, JSON_UNESCAPED_UNICODE);
            }
            if ($updates && ! $dryRun) {
                DB::table('veicoli')->where('id', $current->id)->update($updates + ['updated_at' => $now]);
            }
        }

        if ($nuovi && ! $dryRun) {
            DB::table('veicoli')->insert($nuovi);
        }
    }

    /**
     * Campi da scrivere: quelli vuoti oggi, e quelli cambiati nel file ma mai corretti a mano
     * (valore attuale = valore del precedente import). Senza import precedente si riempiono solo i vuoti.
     *
     * @return array<string, array{vecchio: mixed, nuovo: mixed}>
     */
    private function diff(array $file, array $current, ?array $snapshot): array
    {
        $out = [];
        foreach ($file as $k => $new) {
            if ($new === null || $new === '') {
                continue;
            }
            $cur = $current[$k] ?? null;
            if ($cur === null || $cur === '') {
                $out[$k] = ['vecchio' => null, 'nuovo' => $new];
            } elseif ((string) $cur !== (string) $new && $snapshot !== null && array_key_exists($k, $snapshot) && (string) $snapshot[$k] === (string) $cur) {
                $out[$k] = ['vecchio' => $cur, 'nuovo' => $new];
            }
        }

        return $out;
    }

    private function snapshot(array $fields, ?array $custom = null): array
    {
        $snap = array_filter(array_diff_key($fields, ['codice_esterno' => 1]), fn ($v) => $v !== null && $v !== '');
        if ($custom) {
            $snap['custom'] = $custom;
        }
        ksort($snap);

        return $snap;
    }

    private function esempioModifica(array &$r, string $nome, array $modifiche): void
    {
        if (count($r['esempi_modifiche']) >= 10) {
            return;
        }
        $r['esempi_modifiche'][] = [
            'nome'      => $nome,
            'modifiche' => collect($modifiche)->map(fn ($m, $k) => ['campo' => self::CLIENTE_FIELDS[$k] ?? $k, 'vecchio' => $m['vecchio'], 'nuovo' => $m['nuovo']])->values()->all(),
        ];
    }

    /**
     * Abbina ogni cliente inserito a mano (senza codice) a un cliente del file, in ordine:
     * codice fiscale / P.IVA, telefono, email, nome. Un criterio vale solo se il valore è unico
     * sia tra i clienti presenti sia nel file. Telefono, email e nome sono "da controllare"
     * se i nomi non hanno nemmeno una parola in comune.
     *
     * @return array{0: array<int, int>, 1: array<int, array<string, mixed>>}  [indice gruppo → id cliente, riepilogo per cliente]
     */
    private function riconcilia(int $tenantId, $manuali, array $groups, array $byCodice, array $override): array
    {
        $chiavi = [
            'codice_fiscale' => fn (array $c) => array_values(array_unique(array_filter([$c['codice_fiscale'] ?? null, $c['partita_iva'] ?? null]))),
            'telefono'       => fn (array $c) => ($t = $this->telKey($c['telefono'] ?? null)) ? [$t] : [],
            'email'          => fn (array $c) => ! empty($c['email']) ? [mb_strtolower($c['email'])] : [],
            'nome'           => fn (array $c) => ! empty($c['nome']) ? [$this->nomeKey($c['nome'])] : [],
        ];

        // Indici: valore → gruppi del file / clienti presenti che lo hanno.
        $file = [];
        $presenti = [];
        $codiceToGroup = [];
        foreach ($groups as $gi => $g) {
            if (! empty($g['cliente']['codice_esterno'])) {
                $codiceToGroup[$g['cliente']['codice_esterno']] = $gi;
            }
            // I clienti del file già collegati per codice non sono candidati.
            if (! empty($g['cliente']['codice_esterno']) && isset($byCodice[$g['cliente']['codice_esterno']])) {
                continue;
            }
            foreach ($chiavi as $tipo => $fn) {
                foreach ($fn($g['cliente']) as $val) {
                    $file[$tipo][$val][] = $gi;
                }
            }
        }
        foreach ($manuali as $m) {
            foreach ($chiavi as $tipo => $fn) {
                foreach ($fn((array) $m) as $val) {
                    $presenti[$tipo][$val][] = $m->id;
                }
            }
        }

        $proposte = [];
        foreach ($manuali as $m) {
            if (array_key_exists($m->id, $override) || array_key_exists((string) $m->id, $override)) {
                $codice = (string) ($override[$m->id] ?? $override[(string) $m->id] ?? '');
                $proposte[$m->id] = $codice === ''
                    ? ['gi' => null, 'criterio' => 'escluso']
                    : (isset($codiceToGroup[$codice]) && ! isset($byCodice[$codice])
                        ? ['gi' => $codiceToGroup[$codice], 'criterio' => 'manuale']
                        : ['gi' => null, 'criterio' => 'codice_non_trovato']);
                continue;
            }
            $candidati = null;
            foreach ($chiavi as $tipo => $fn) {
                foreach ($fn((array) $m) as $val) {
                    $nelFile = $file[$tipo][$val] ?? [];
                    if (count($nelFile) === 1 && count($presenti[$tipo][$val] ?? []) === 1) {
                        $proposte[$m->id] = ['gi' => $nelFile[0], 'criterio' => $tipo];
                        break 2;
                    }
                    // Più clienti del file con lo stesso dato (doppioni nel gestionale): non si sceglie da soli,
                    // ma si propongono all'utente.
                    if ($candidati === null && count($nelFile) > 1) {
                        $candidati = ['tipo' => $tipo, 'gruppi' => array_slice($nelFile, 0, 5)];
                    }
                }
            }
            $proposte[$m->id] ??= ['gi' => null, 'criterio' => $candidati['tipo'] ?? null, 'candidati' => $candidati['gruppi'] ?? []];
        }

        // Una scelta fatta a mano vince sugli abbinamenti automatici allo stesso cliente del file.
        $sceltiAMano = collect($proposte)->filter(fn ($p) => $p['criterio'] === 'manuale')->pluck('gi')->flip();
        foreach ($proposte as $id => $p) {
            if ($p['gi'] !== null && $p['criterio'] !== 'manuale' && isset($sceltiAMano[$p['gi']])) {
                $proposte[$id] = ['gi' => null, 'criterio' => null];
            }
        }

        // Due clienti presenti sullo stesso cliente del file: nessuno dei due viene abbinato da solo.
        $perGruppo = collect($proposte)->filter(fn ($p) => $p['gi'] !== null)->countBy(fn ($p) => $p['gi']);

        $pratiche = DB::table('pratiche')->where('tenant_id', $tenantId)->whereIn('cliente_id', $manuali->keys())
            ->selectRaw('cliente_id, count(*) as n')->groupBy('cliente_id')->pluck('n', 'cliente_id');

        $abbinati = [];
        $report = [];
        foreach ($manuali as $m) {
            $p = $proposte[$m->id];
            $match = null;
            $stato = match (true) {
                $p['criterio'] === 'escluso' => 'escluso',
                $p['criterio'] === 'codice_non_trovato' => 'codice_non_trovato',
                ! empty($p['candidati']) => 'piu_candidati',
                default => 'nessuno',
            };

            if ($p['gi'] !== null) {
                $g = $groups[$p['gi']]['cliente'];
                if ($perGruppo[$p['gi']] > 1) {
                    $stato = 'conflitto';
                } else {
                    $abbinati[$p['gi']] = $m->id;
                    $sicuro = $p['criterio'] === 'manuale' || $p['criterio'] === 'codice_fiscale' || $this->nomiCompatibili($m->nome, $g['nome'] ?? '');
                    $stato = $sicuro ? 'abbinato' : 'da_controllare';
                }
                $match = [
                    'codice'   => $g['codice_esterno'] ?? null,
                    'nome'     => $g['nome'] ?? null,
                    'telefono' => $g['telefono'] ?? null,
                    'email'    => $g['email'] ?? null,
                    'codice_fiscale' => $g['codice_fiscale'] ?? null,
                ];
            }

            $report[] = [
                'id'             => $m->id,
                'nome'           => $m->nome,
                'telefono'       => $m->telefono,
                'email'          => $m->email,
                'codice_fiscale' => $m->codice_fiscale,
                'pratiche'       => (int) ($pratiche[$m->id] ?? 0),
                'stato'          => $stato,
                'criterio'       => $p['criterio'],
                'match'          => $match,
                'candidati'      => array_map(fn ($gi) => [
                    'codice'   => $groups[$gi]['cliente']['codice_esterno'] ?? null,
                    'nome'     => $groups[$gi]['cliente']['nome'] ?? null,
                    'telefono' => $groups[$gi]['cliente']['telefono'] ?? null,
                ], $p['candidati'] ?? []),
            ];
        }

        // Prima quelli che chiedono attenzione.
        $ordine = ['conflitto' => 0, 'piu_candidati' => 1, 'da_controllare' => 2, 'codice_non_trovato' => 3, 'nessuno' => 4, 'abbinato' => 5, 'escluso' => 6];
        usort($report, fn ($a, $b) => [$ordine[$a['stato']], $a['nome']] <=> [$ordine[$b['stato']], $b['nome']]);

        return [$abbinati, $report];
    }

    private function telKey(?string $telefono): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $telefono);

        return strlen($digits) >= 8 ? substr($digits, -9) : null;
    }

    /** "Mario Rossi" e "ROSSI MARIO ." → stessa chiave. */
    private function nomeKey(string $nome): string
    {
        $parole = array_filter(preg_split('/[^a-z0-9]+/', mb_strtolower(strtr($nome, ['à' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u']))));
        sort($parole);

        return implode(' ', $parole);
    }

    private function nomiCompatibili(string $a, string $b): bool
    {
        $pa = array_filter(explode(' ', $this->nomeKey($a)), fn ($p) => strlen($p) >= 3);
        $pb = array_filter(explode(' ', $this->nomeKey($b)), fn ($p) => strlen($p) >= 3);

        return (bool) array_intersect($pa, $pb);
    }

    // ── Righe → clienti raggruppati ──────────────────────────────────────────

    /** @return array<int, array{cliente: array, custom: array, veicoli: array, riga: int}> */
    private function group(array $rows, array $mapping, array $types): array
    {
        $groups = [];

        foreach ($rows as $i => $row) {
            $riga = $i + 2; // +1 intestazione, +1 base 1: il numero che si vede in Excel
            [$cliente, $custom, $veicolo] = $this->mapRow($row, $mapping, $types, $riga);

            $key = ! empty($cliente['codice_esterno']) ? 'c:' . $cliente['codice_esterno'] : "r:{$i}";

            if (! isset($groups[$key])) {
                $groups[$key] = ['cliente' => $cliente, 'custom' => $custom, 'veicoli' => [], 'riga' => $riga];
            } else {
                foreach ($cliente as $k => $v) {
                    $groups[$key]['cliente'][$k] ??= $v;
                }
                $groups[$key]['custom'] += $custom;
            }

            if ($veicolo !== null) {
                $vKey = $veicolo['telaio'] ?: 'targa:' . $veicolo['targa'];
                if (isset($groups[$key]['veicoli'][$vKey])) {
                    foreach ($veicolo as $k => $v) {
                        $groups[$key]['veicoli'][$vKey][$k] ??= $v;
                    }
                } else {
                    $groups[$key]['veicoli'][$vKey] = $veicolo;
                }
            }
        }

        return array_values($groups);
    }

    /** @return array{0: array<string, ?string>, 1: array<string, mixed>, 2: ?array<string, mixed>} */
    private function mapRow(array $row, array $mapping, array $types, int $riga): array
    {
        $cliente = [];
        $custom = [];
        $veicolo = [];

        foreach ($mapping as $header => $target) {
            $raw = $this->clean($row[$header] ?? '');
            if ($raw === null) {
                continue;
            }

            if (str_starts_with($target, 'veicolo.')) {
                $field = substr($target, 8);
                $veicolo[$field] ??= $this->normalizeVeicolo($field, $raw, $riga);
            } elseif (str_starts_with($target, 'custom_fields.')) {
                $field = substr($target, 14);
                $custom[$field] ??= $this->normalizeCustom($types[$field] ?? 'text', $raw, $riga);
            } elseif (in_array($target, self::CONCAT_FIELDS, true) && isset($cliente[$target])) {
                $cliente[$target] .= ' ' . $raw;
            } else {
                $cliente[$target] ??= $raw;
            }
        }

        foreach ($cliente as $k => $v) {
            $cliente[$k] = $this->normalizeCliente($k, $v, $riga);
        }
        $cliente = array_intersect_key($cliente + array_fill_keys(array_keys(self::CLIENTE_FIELDS), null), self::CLIENTE_FIELDS);
        $custom = array_filter($custom, fn ($v) => $v !== null);
        $veicolo = array_filter($veicolo, fn ($v) => $v !== null);

        // Un veicolo esiste solo se ha targa o telaio.
        $veicolo = empty($veicolo['targa']) && empty($veicolo['telaio'])
            ? null
            : $veicolo + array_fill_keys(array_keys(self::VEICOLO_FIELDS), null);

        return [$cliente, $custom, $veicolo];
    }

    private function clean(mixed $value): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', (string) $value));

        return $value === '' || in_array(mb_strtolower($value), self::PLACEHOLDERS, true) ? null : $value;
    }

    private function normalizeCliente(string $field, ?string $v, int $riga): ?string
    {
        if ($v === null) {
            return null;
        }

        $v = match ($field) {
            // Alcuni gestionali chiudono la ragione sociale con " ." — si toglie.
            'nome'           => mb_substr(rtrim(preg_replace('/\s+\.$/', '', $v)), 0, 255),
            'codice_esterno' => mb_substr(preg_replace('/^(\d+)\.0+$/', '$1', $v), 0, 50),
            'email'          => mb_strtolower($v),
            'telefono'       => $this->normalizeTelefono($v),
            'codice_fiscale' => mb_substr(strtoupper(str_replace(' ', '', $v)), 0, 16),
            'partita_iva'    => mb_substr(preg_replace('/[^0-9A-Z]/', '', strtoupper($v)), 0, 20),
            'cap'            => mb_substr($v, 0, 10),
            'citta'          => mb_substr($v, 0, 100),
            'provincia'      => mb_substr(strtoupper($v), 0, 5),
            default          => mb_substr($v, 0, 255),
        };

        if ($field === 'email' && $v !== null && ! filter_var($v, FILTER_VALIDATE_EMAIL)) {
            $this->avviso('email', 'Email non valide: il cliente viene importato senza email', $riga);
            return null;
        }

        return $v === '' ? null : $v;
    }

    private function normalizeTelefono(string $v): ?string
    {
        $plus = str_starts_with($v, '+') || str_starts_with($v, '00');
        $digits = preg_replace('/\D/', '', $v);
        if (str_starts_with($v, '00')) {
            $digits = substr($digits, 2);
        }

        return strlen($digits) < 6 ? null : mb_substr(($plus ? '+' : '') . $digits, 0, 30);
    }

    private function normalizeVeicolo(string $field, string $v, int $riga): mixed
    {
        return match (true) {
            in_array($field, self::VEICOLO_DATES, true) => $this->normalizeDate($v, $riga),
            $field === 'targa'  => mb_substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($v)), 0, 20) ?: null,
            $field === 'telaio' => mb_substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($v)), 0, 30) ?: null,
            $field === 'km'     => ($km = (int) preg_replace('/\D/', '', explode(',', $v)[0])) > 0 ? min($km, 4_000_000_000) : null,
            $field === 'marca'  => mb_substr($v, 0, 50),
            $field === 'modello' => mb_substr($v, 0, 100),
            default             => mb_substr($v, 0, 255),
        };
    }

    private function normalizeCustom(string $type, string $v, int $riga): mixed
    {
        return match ($type) {
            'date'    => $this->normalizeDate($v, $riga),
            'number'  => is_numeric($n = str_contains($v, ',') ? str_replace(['.', ','], ['', '.'], $v) : $v) ? $n + 0 : null,
            'boolean' => in_array(mb_strtolower($v), ['1', 'si', 'sì', 'yes', 'true', 'x', 'vero'], true),
            default   => mb_substr($v, 0, 1000),
        };
    }

    /** Date in formato Excel (numero seriale), gg/mm/aaaa o aaaa-mm-gg → aaaa-mm-gg. */
    private function normalizeDate(string $v, int $riga): ?string
    {
        if (is_numeric($v) && (float) $v >= 1 && (float) $v < 2958466) {
            return ExcelDate::excelToDateTimeObject((float) $v)->format('Y-m-d');
        }
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $v, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }
        if (preg_match('#^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{2}|\d{4})\b#', $v, $m)) {
            $y = strlen($m[3]) === 2 ? (int) $m[3] + ((int) $m[3] > 50 ? 1900 : 2000) : (int) $m[3];
            if (checkdate((int) $m[2], (int) $m[1], $y)) {
                return sprintf('%04d-%02d-%02d', $y, $m[2], $m[1]);
            }
        }

        $this->avviso('data', 'Date non riconosciute: il campo resta vuoto', $riga);

        return null;
    }

    private function avviso(string $tipo, string $messaggio, int $riga): void
    {
        $this->avvisi[$tipo] ??= ['tipo' => $tipo, 'messaggio' => $messaggio, 'conteggio' => 0, 'righe' => []];
        $this->avvisi[$tipo]['conteggio']++;
        if (count($this->avvisi[$tipo]['righe']) < self::MAX_RIGHE_AVVISO) {
            $this->avvisi[$tipo]['righe'][] = $riga;
        }
    }

    /** I campi data dei clienti con un promemoria attivo: importarli fa partire messaggi. */
    private function avvisiAutomazioni(int $tenantId, array $mapping): void
    {
        $campi = collect($mapping)->filter(fn ($t) => str_starts_with($t, 'custom_fields.'))
            ->map(fn ($t) => substr($t, 14))->values();
        if ($campi->isEmpty()) {
            return;
        }

        $attive = Automation::acrossAllTenants()->where('tenant_id', $tenantId)
            ->where('trigger_type', 'cliente_date_field')->where('is_active', true)
            ->whereIn('watched_field', $campi)->pluck('watched_field')->unique();

        foreach ($attive as $campo) {
            $this->avvisi["automazione_{$campo}"] = [
                'tipo'      => 'automazione',
                'messaggio' => "Il campo \"{$campo}\" ha un promemoria automatico attivo: i clienti importati riceveranno i messaggi alle scadenze",
                'conteggio' => 0,
                'righe'     => [],
            ];
        }
    }
}
