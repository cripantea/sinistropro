<?php

namespace App\Support;

/** Come si scrive un valore di un campo in un testo (messaggi, PDF): date sempre all'italiana. */
class Formato
{
    /** 2026-10-12 → 12/10/2026, 2026-10-12T15:30 → 12/10/2026 15:30; gli altri valori restano come sono. */
    public static function valore(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Sì' : 'No';
        }
        if (! is_scalar($value)) {
            return '';
        }
        $text = (string) $value;
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2}))?/', $text, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            $ora = isset($m[4]) && "{$m[4]}:{$m[5]}" !== '00:00' ? " {$m[4]}:{$m[5]}" : '';

            return "{$m[3]}/{$m[2]}/{$m[1]}{$ora}";
        }

        return $text;
    }
}
