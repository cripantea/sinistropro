<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * L'agenzia scrive il template con le variabili di SinistroPro ({nome_cliente}…); Meta vuole {{1}}, {{2}}…
 * con un esempio per ognuna. Qui si converte e si controllano le regole di Meta.
 */
class TestoTemplateWhatsapp
{
    private const ESEMPI = [
        '{nome_cliente}' => 'Mario Rossi',
        '{nome_destinatario}' => 'Mario Rossi',
        '{numero_pratica}' => '12345',
        '{stato_corrente}' => 'In lavorazione',
        '{data_perizia}' => '12/11/2026 alle 10:30',
        '{nome_tenant}' => 'Agenzia',
        '{compagnia}' => 'AXA',
        '{targa}' => 'AB123CD',
        '{giorni_in_stato}' => '7',
        '{data_scadenza}' => '30/11/2026',
    ];

    /** @return array{body: string, variabili: array<string, string>, esempi: array<int, string>} */
    public static function converti(string $corpo, string $nomeAgenzia): array
    {
        $variabili = [];
        $body = preg_replace_callback('/\{[a-z_]+(?:\.[a-z0-9_]+)?\}/i', function ($m) use (&$variabili) {
            $posizione = array_search($m[0], $variabili, true);
            if ($posizione === false) {
                $variabili[(string) (count($variabili) + 1)] = $m[0];
                $posizione = (string) count($variabili);
            }

            return '{{'.$posizione.'}}';
        }, trim($corpo));

        // Anche chi scrive direttamente {{1}} va bene: quelle posizioni restano "da compilare".
        preg_match_all('/\{\{(\d+)\}\}/', $body, $tutte);
        $numero = $tutte[1] ? max(array_map('intval', $tutte[1])) : 0;

        if (preg_match('/^\{\{\d+\}\}|\{\{\d+\}\}[\s.!?]*$/u', $body)) {
            throw ValidationException::withMessages(['corpo' => 'Meta non accetta un messaggio che inizia o finisce con una variabile: aggiungi del testo prima e dopo (es. "Gentile {nome_cliente}, …" e una frase finale).']);
        }

        $esempi = [];
        for ($i = 1; $i <= $numero; $i++) {
            $codice = $variabili[(string) $i] ?? null;
            $esempi[] = $codice === '{nome_tenant}' ? $nomeAgenzia : (self::ESEMPI[$codice] ?? 'esempio');
        }

        return ['body' => $body, 'variabili' => $variabili, 'esempi' => $esempi];
    }

    /** Nome per Meta: minuscole, numeri e underscore. */
    public static function nome(string $nome): string
    {
        return trim(preg_replace('/_+/', '_', preg_replace('/[^a-z0-9_]/', '_', mb_strtolower(trim($nome)))), '_');
    }
}
