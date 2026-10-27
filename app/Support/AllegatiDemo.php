<?php

namespace App\Support;

use App\Models\Pratica;
use Fpdf\Fpdf;

/**
 * File di esempio per i sinistri dell'account demo: documenti PDF di una pagina e foto
 * segnaposto. Tutto è marcato "documento di esempio" e usa solo i dati finti del sinistro.
 */
class AllegatiDemo
{
    /**
     * @param  int  $fase  0 aperto, 1 perizia, 2 attesa documenti, 3 liquidazione, 4 chiuso
     * @return array<int, array{nome: string, estensione: string, categoria: string, contenuto: string}>
     */
    public static function per(Pratica $pratica, int $fase): array
    {
        $file = [
            self::pdf('Denuncia sinistro', 'Denuncia e Sinistro', $pratica, [
                'Il cliente denuncia il sinistro avvenuto il ' . $pratica->created_at->copy()->subDays(2)->format('d/m/Y') . '.',
                'Documento firmato e consegnato in agenzia.',
            ]),
            self::foto('Foto danno 1', $pratica, 1),
            self::foto('Foto danno 2', $pratica, 2),
        ];

        if ($pratica->veicolo) {
            $file[] = self::pdf('CAI constatazione amichevole', 'Denuncia e Sinistro', $pratica, [
                'Modulo CAI compilato e firmato da entrambi i conducenti.',
                'Veicolo: ' . trim("{$pratica->veicolo->targa} {$pratica->veicolo->marca} {$pratica->veicolo->modello}") . '.',
            ]);
        }

        if ($fase >= 1) {
            $file[] = self::pdf('Relazione peritale', 'Perizie e Valutazioni', $pratica, [
                'Sopralluogo eseguito. Danni compatibili con la dinamica dichiarata.',
                'Stima del danno: ' . number_format(800 + ($pratica->id * 137) % 5200, 0, ',', '.') . ' euro.',
            ]);
        }

        if ($fase >= 3) {
            $file[] = self::pdf('Quietanza di liquidazione', 'Contabilità e Fatture', $pratica, [
                'La compagnia ' . ($pratica->compagnia ?: 'assicurativa') . ' liquida il danno come da perizia.',
                'Quietanza firmata dal cliente.',
            ]);
        }

        return $file;
    }

    private static function pdf(string $titolo, string $categoria, Pratica $pratica, array $righe): array
    {
        $p = new Fpdf('P', 'mm', 'A4');
        $p->AddPage();
        $p->SetFont('Helvetica', 'B', 9);
        $p->SetTextColor(180, 30, 30);
        $p->Cell(0, 6, 'DOCUMENTO DI ESEMPIO - dati fittizi', 0, 1, 'R');
        $p->SetTextColor(20, 20, 20);
        $p->SetFont('Helvetica', 'B', 16);
        $p->Ln(8);
        $p->Cell(0, 10, self::txt($titolo), 0, 1);
        $p->SetDrawColor(30, 58, 138);
        $p->Line(10, $p->GetY() + 1, 200, $p->GetY() + 1);
        $p->Ln(6);
        $p->SetFont('Helvetica', '', 11);
        $dati = [
            'Sinistro' => '#' . $pratica->id,
            'Cliente' => $pratica->cliente?->nome ?? '-',
            'Compagnia' => $pratica->compagnia ?: '-',
            'Data' => $pratica->created_at->format('d/m/Y'),
        ];
        foreach ($dati as $k => $v) {
            $p->SetFont('Helvetica', 'B', 11);
            $p->Cell(35, 7, self::txt($k));
            $p->SetFont('Helvetica', '', 11);
            $p->Cell(0, 7, self::txt($v), 0, 1);
        }
        $p->Ln(6);
        foreach ($righe as $riga) {
            $p->MultiCell(0, 6, self::txt($riga));
            $p->Ln(1);
        }

        return ['nome' => $titolo, 'estensione' => 'pdf', 'categoria' => $categoria, 'contenuto' => $p->Output('S')];
    }

    /** Foto segnaposto (JPEG): sfondo grigio con la scritta "foto di esempio". */
    private static function foto(string $titolo, Pratica $pratica, int $n): array
    {
        $img = imagecreatetruecolor(800, 600);
        $toni = [[96, 108, 122], [120, 110, 98]];
        [$r, $g, $b] = $toni[($pratica->id + $n) % 2];
        for ($y = 0; $y < 600; $y++) {
            $c = imagecolorallocate($img, min(255, $r + intdiv($y, 12)), min(255, $g + intdiv($y, 12)), min(255, $b + intdiv($y, 12)));
            imageline($img, 0, $y, 800, $y, $c);
        }
        $bianco = imagecolorallocate($img, 255, 255, 255);
        imagerectangle($img, 40, 40, 760, 560, $bianco);
        imagestring($img, 5, 300, 270, 'FOTO DI ESEMPIO', $bianco);
        imagestring($img, 4, 300, 300, "Sinistro #{$pratica->id} - foto {$n}", $bianco);

        ob_start();
        imagejpeg($img, null, 80);
        imagedestroy($img);

        return ['nome' => $titolo, 'estensione' => 'jpg', 'categoria' => 'Foto e Media', 'contenuto' => (string) ob_get_clean()];
    }

    private static function txt(string $s): string
    {
        return iconv('UTF-8', 'windows-1252//IGNORE', $s) ?: $s;
    }
}
