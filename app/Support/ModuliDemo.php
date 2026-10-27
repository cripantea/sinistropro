<?php

namespace App\Support;

use Fpdf\Fpdf as FPDF;

/**
 * Moduli PDF dimostrativi (stessa struttura di quelli delle carrozzerie, intestati a uno studio
 * di fantasia). Ogni modulo restituisce il PDF e lo schema dei campi già posizionato: le
 * posizioni si calcolano mentre si disegna, quindi combaciano sempre con le caselle.
 */
class ModuliDemo
{
    public const INTESTAZIONE = 'STUDIO DEMO ASSICURAZIONI';
    public const PIEDE = 'Studio Demo Assicurazioni - Via Roma 1, 20121 Milano - Tel. 02 0000000 - dati di esempio';
    public const CARROZZERIA = 'Carrozzeria Esempio S.r.l., Via Verdi 10, 20121 Milano, P. IVA 00000000000';

    private FPDF $pdf;
    private array $campi = [];
    private float $y = 0;
    private string $titolo = '';
    private const L = 15;   // margine sinistro (mm)
    private const W = 180;  // larghezza utile (mm)

    /** @return array<int, array{nome: string, file: string, pdf: string, schema: array}> */
    public static function tutti(): array
    {
        return [
            (new self)->denuncia(),
            (new self)->cessione(),
            (new self)->noleggio(),
            (new self)->scoperti(),
        ];
    }

    public function denuncia(): array
    {
        $this->inizia('MODULO DENUNCIA SINISTRO');

        $this->sezione('DATI DEL CLIENTE');
        $this->riga([['Cognome e nome', 'cognome_nome', 1]]);
        $this->riga([['Indirizzo di residenza', 'indirizzo_residenza', 1]]);
        $this->riga([['CAP', 'cap', .2], ['Città', 'citta', .6], ['Provincia', 'provincia', .2]]);
        $this->riga([['Telefono', 'telefono', .4], ['E-mail', 'email', .6]]);

        $this->sezione('DATI DEL VEICOLO');
        $this->riga([['Marca veicolo', 'marca_veicolo', .5], ['Modello veicolo', 'modello_veicolo', .5]]);
        $this->riga([['Targa veicolo', 'targa_veicolo', .5], ['Data prima immatricolazione', 'data_immatricolazione', .5, 'date']]);

        $this->sezione('DATI DEL SINISTRO');
        $this->riga([['Data sinistro', 'data_sinistro', .5, 'date'], ['Comune del sinistro', 'comune_sinistro', .5]]);
        $this->riga([['Indirizzo del sinistro', 'luogo_sinistro', 1]]);
        $this->riga([['Data apertura sinistro', 'data_apertura_sinistro', .33, 'date'], ['Numero sinistro', 'numero_sinistro', .33], ['Compagnia assicurativa', 'compagnia_assicurativa', .34]]);

        $this->sezione('TIPOLOGIA DI SINISTRO (UNA SOLA VOCE)');
        $this->scelta('tipologia_sinistro', 'Tipologia di sinistro', ['R.C.T. (con C.I.D. compilato)', 'Minicollisione (minikasko)', 'Kasko', 'Atto vandalico', 'Eventi naturali (grandine, ecc.)', 'Cristalli', 'Altro']);

        $this->sezione('DESCRIZIONE DELL\'ACCADUTO');
        $this->areaTesto('descrizione_accaduto', 'Descrizione dell\'accaduto', 4);

        $this->firme();

        return $this->fine('Modulo denuncia sinistro', 'DEMO_MODULO_DENUNCIA_SINISTRO.pdf');
    }

    public function cessione(): array
    {
        $this->inizia('CESSIONE DEL CREDITO');

        $this->sezione('IL CEDENTE');
        $this->riga([['Il sottoscritto (cognome e nome)', 'cedente', .6], ['Codice fiscale', 'codice_fiscale', .4]]);
        $this->riga([['Residente in', 'indirizzo_residenza', .6], ['Città', 'citta', .25], ['Prov.', 'provincia', .15]]);

        $this->sezione('VEICOLO E SINISTRO');
        $this->riga([['Targa veicolo', 'targa_veicolo', .3], ['Marca e modello', 'marca_modello', .7]]);
        $this->riga([['Data sinistro', 'data_sinistro', .3, 'date'], ['Numero sinistro', 'numero_sinistro', .35], ['Compagnia assicurativa', 'compagnia_assicurativa', .35]]);

        $this->sezione('DICHIARAZIONE');
        $this->paragrafo('Il sottoscritto, proprietario del veicolo sopra indicato e danneggiato nel sinistro descritto, dichiara di voler '
            . 'effettuare le riparazioni presso ' . self::CARROZZERIA . ' (cessionaria).');
        $this->paragrafo('Con il presente atto CEDE alla cessionaria, ai sensi degli artt. 1260 e seguenti del codice civile, il credito '
            . 'vantato nei confronti della compagnia assicurativa per il risarcimento dei danni al veicolo, fino alla concorrenza '
            . 'dell\'importo delle fatture di riparazione. La cessionaria è autorizzata a trattare e incassare il risarcimento.');
        $this->paragrafo('Documento di esempio: testo e dati della carrozzeria sono fittizi.');

        $this->firme('Firma del cedente', 'Firma della cessionaria');

        return $this->fine('Cessione del credito', 'DEMO_CESSIONE_CREDITO.pdf');
    }

    public function noleggio(): array
    {
        $this->inizia('GIUSTIFICATIVO DEL NOLEGGIO');

        $this->sezione('DATI DEL CLIENTE');
        $this->riga([['Cognome e nome', 'cognome_nome', .6], ['Telefono', 'telefono', .4]]);
        $this->riga([['Targa veicolo danneggiato', 'targa_veicolo', .4], ['Numero sinistro', 'numero_sinistro', .3], ['Compagnia assicurativa', 'compagnia_assicurativa', .3]]);

        $this->sezione('VEICOLO SOSTITUTIVO');
        $this->riga([['Modello veicolo sostitutivo', 'veicolo_sostitutivo', .5], ['Targa veicolo sostitutivo', 'targa_sostitutivo', .5]]);
        $this->riga([['Noleggio dal', 'noleggio_dal', .5, 'date'], ['Noleggio al', 'noleggio_al', .5, 'date']]);

        $this->sezione('MOTIVO DEL NOLEGGIO');
        $this->caselle([
            ['uso_lavoro', 'Il veicolo è necessario per l\'attività lavorativa'],
            ['uso_familiare', 'Il veicolo è necessario per esigenze familiari'],
            ['fermo_tecnico', 'Fermo tecnico per riparazione'],
        ]);
        $this->areaTesto('note_noleggio', 'Note', 3);

        $this->firme();

        return $this->fine('Giustificativo noleggio', 'DEMO_GIUSTIFICATIVO_NOLEGGIO.pdf');
    }

    public function scoperti(): array
    {
        $this->inizia('DICHIARAZIONE DI SCOPERTI ASSICURATIVI');

        $this->sezione('DATI DEL CLIENTE');
        $this->riga([['Cognome e nome', 'cognome_nome', .6], ['Codice fiscale', 'codice_fiscale', .4]]);
        $this->riga([['Targa veicolo', 'targa_veicolo', .3], ['Numero sinistro', 'numero_sinistro', .35], ['Compagnia assicurativa', 'compagnia_assicurativa', .35]]);

        $this->sezione('DICHIARAZIONE');
        $this->paragrafo('Il sottoscritto dichiara di essere stato informato che la polizza prevede i seguenti importi a proprio carico, '
            . 'che saranno saldati direttamente alla consegna del veicolo riparato:');
        $this->caselle([
            ['franchigia', 'Franchigia'],
            ['scoperto', 'Scoperto percentuale'],
            ['nessuno_scoperto', 'Nessuno scoperto o franchigia'],
        ]);
        $this->riga([['Importo a carico del cliente (€)', 'importo_scoperto', .5], ['Percentuale scoperto (%)', 'percentuale_scoperto', .5]]);

        $this->firme();

        return $this->fine('Dichiarazione scoperti assicurativi', 'DEMO_DICHIARAZIONE_SCOPERTI.pdf');
    }

    // ── Disegno ─────────────────────────────────────────────────────────

    private function inizia(string $titolo): void
    {
        $this->pdf = new FPDF('P', 'mm', 'A4');
        $this->pdf->SetAutoPageBreak(false);
        $this->titolo = $titolo;
        $this->campi = [];
        $this->pagina();
    }

    private function pagina(): void
    {
        $p = $this->pdf;
        $p->AddPage();
        $p->SetDrawColor(30, 58, 138);
        $p->SetFillColor(30, 58, 138);
        $p->Rect(self::L, 12, 9, 9, 'F');
        $p->SetTextColor(255, 255, 255);
        $p->SetFont('Helvetica', 'B', 10);
        $p->SetXY(self::L, 13.5);
        $p->Cell(9, 6, 'SD', 0, 0, 'C');
        $p->SetTextColor(20, 20, 20);
        $p->SetFont('Helvetica', 'B', 12);
        $p->SetXY(self::L + 12, 13.5);
        $p->Cell(120, 6, self::INTESTAZIONE);
        $p->SetLineWidth(.3);
        $p->Line(self::L, 25, self::L + self::W, 25);
        $p->SetFont('Helvetica', '', 14);
        $p->SetXY(self::L, 30);
        $p->Cell(self::W, 8, $this->txt($this->titolo), 0, 0, 'C');
        $p->SetLineWidth(.2);
        $p->SetDrawColor(60, 60, 60);
        $p->SetFont('Helvetica', '', 7);
        $p->SetTextColor(110, 110, 110);
        $p->SetXY(self::L, 285);
        $p->Cell(self::W - 25, 4, $this->txt(self::PIEDE));
        $p->Cell(25, 4, 'Pagina ' . $p->PageNo(), 0, 0, 'R');
        $p->SetTextColor(20, 20, 20);
        $this->y = 44;
    }

    private function spazio(float $mm): void
    {
        if ($this->y + $mm > 278) {
            $this->pagina();
        }
    }

    private function sezione(string $titolo): void
    {
        $this->spazio(16);
        $p = $this->pdf;
        $p->SetFillColor(38, 38, 38);
        $p->Rect(self::L, $this->y, self::W, 6, 'F');
        $p->SetTextColor(255, 255, 255);
        $p->SetFont('Helvetica', '', 8.5);
        $p->SetXY(self::L + 2, $this->y + .5);
        $p->Cell(self::W, 5, $this->txt($titolo));
        $p->SetTextColor(20, 20, 20);
        $this->y += 6;
    }

    /** @param array<int, array{0: string, 1: string, 2: float, 3?: string}> $celle etichetta, nome campo, quota di larghezza, tipo */
    private function riga(array $celle): void
    {
        $this->spazio(11);
        $p = $this->pdf;
        $x = self::L;
        foreach ($celle as $c) {
            [$etichetta, $nome, $quota] = $c;
            $w = self::W * $quota;
            $p->Rect($x, $this->y, $w, 11);
            $p->SetFont('Helvetica', '', 6.5);
            $p->SetTextColor(90, 90, 90);
            $p->SetXY($x + 1.5, $this->y + .8);
            $p->Cell($w - 3, 3, $this->txt($etichetta));
            $p->SetTextColor(20, 20, 20);
            $this->campo($nome, $etichetta, $c[3] ?? 'text', $x + 1.5, $this->y + 5, $w - 3);
            $x += $w;
        }
        $this->y += 11;
    }

    private function scelta(string $nome, string $etichetta, array $opzioni): void
    {
        $posizioni = [];
        foreach ($opzioni as $opzione) {
            $this->spazio(7);
            $posizioni[$opzione] = $this->casella($opzione);
        }
        $primo = reset($posizioni);
        $this->campi[] = [
            'name' => $nome, 'label' => $etichetta, 'type' => 'radio', 'required' => false,
            ...$primo, 'options' => $opzioni, 'positions' => $posizioni,
        ];
        $this->y += 2;
    }

    /** @param array<int, array{0: string, 1: string}> $voci nome campo, testo */
    private function caselle(array $voci): void
    {
        foreach ($voci as [$nome, $testo]) {
            $this->spazio(7);
            $pos = $this->casella($testo);
            $this->campi[] = ['name' => $nome, 'label' => $testo, 'type' => 'checkbox', 'required' => false, ...$pos];
        }
        $this->y += 2;
    }

    /** Disegna una casella con il suo testo e ritorna la posizione della X (in % della pagina). */
    private function casella(string $testo): array
    {
        $p = $this->pdf;
        $this->y += 2;
        $p->Rect(self::L + 3, $this->y, 4, 4);
        $p->SetFont('Helvetica', '', 9);
        $p->SetXY(self::L + 10, $this->y - .3);
        $p->Cell(self::W - 12, 4.6, $this->txt($testo));
        $pos = $this->percento(self::L + 3, $this->y - .5, 4);
        $this->y += 5;

        return $pos;
    }

    private function areaTesto(string $nome, string $etichetta, int $righe): void
    {
        $h = 6 * $righe + 2;
        $this->spazio($h);
        $p = $this->pdf;
        $p->Rect(self::L, $this->y, self::W, $h);
        $p->SetDrawColor(210, 210, 210);
        for ($i = 1; $i < $righe; $i++) {
            $p->Line(self::L + 2, $this->y + 1 + 6 * $i, self::L + self::W - 2, $this->y + 1 + 6 * $i);
        }
        $p->SetDrawColor(60, 60, 60);
        $this->campo($nome, $etichetta, 'textarea', self::L + 2, $this->y + 1.5, self::W - 4);
        $this->y += $h + 2;
    }

    private function paragrafo(string $testo): void
    {
        $this->spazio(14);
        $p = $this->pdf;
        $p->SetFont('Helvetica', '', 9);
        $p->SetXY(self::L, $this->y + 2);
        $p->MultiCell(self::W, 4.6, $this->txt($testo), 0, 'J');
        $this->y = $p->GetY() + 1;
    }

    private function firme(string $sinistra = 'Firma del cliente', string $destra = 'Timbro e firma dello studio'): void
    {
        $this->spazio(26);
        $this->y += 4;
        $this->riga([['Luogo e data', 'luogo_data', .5]]);
        $p = $this->pdf;
        $this->y += 10;
        $p->SetFont('Helvetica', '', 8);
        foreach ([[self::L, $sinistra], [self::L + 100, $destra]] as [$x, $testo]) {
            $p->Line($x, $this->y, $x + 80, $this->y);
            $p->SetXY($x, $this->y + 1);
            $p->Cell(80, 4, $this->txt($testo), 0, 0, 'C');
        }
        $this->y += 8;
    }

    private function campo(string $nome, string $etichetta, string $tipo, float $x, float $y, float $w): void
    {
        $this->campi[] = ['name' => $nome, 'label' => $etichetta, 'type' => $tipo, 'required' => false, ...$this->percento($x, $y, $w)];
    }

    /** Coordinate in % della pagina A4, come le legge PdfFormFillerService. */
    private function percento(float $x, float $y, float $w): array
    {
        return [
            'page' => $this->pdf->PageNo(),
            'x' => round($x / 210 * 100, 2),
            'y' => round($y / 297 * 100, 2),
            'w' => round($w / 210 * 100, 2),
        ];
    }

    private function fine(string $nome, string $file): array
    {
        return ['nome' => $nome, 'file' => $file, 'pdf' => $this->pdf->Output('S'), 'schema' => $this->campi];
    }

    private function txt(string $s): string
    {
        return iconv('UTF-8', 'windows-1252//IGNORE', $s) ?: $s;
    }
}
