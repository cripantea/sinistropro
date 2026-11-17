<?php

namespace App\Support;

/**
 * Decodifica oggetti e nomi delle email scritti come "=?UTF-8?B?...?=" (RFC 2047).
 * Senza l'estensione PHP imap (assente in produzione) php-imap li lascia così; le funzioni
 * di PHP da sole rovinano gli accenti già in UTF-8, quindi si decodificano solo i pezzi codificati.
 */
class MimeHeader
{
    public static function decode(?string $value): ?string
    {
        if ($value === null || ! str_contains($value, '=?')) {
            return $value === null ? null : self::senzaVirgolette($value);
        }

        // Spazi tra due pezzi codificati consecutivi non fanno parte del testo.
        $value = preg_replace('/(\?=)\s+(=\?)/', '$1$2', $value);

        $decoded = preg_replace_callback(
            '/=\?([^?]+)\?([bBqQ])\?([^?]*)\?=/',
            function (array $m) {
                $testo = strtoupper($m[2]) === 'B'
                    ? base64_decode($m[3])
                    : quoted_printable_decode(str_replace('_', ' ', $m[3]));
                $charset = strtoupper(explode('*', $m[1])[0]);
                if ($charset !== 'UTF-8' && $testo !== false) {
                    $convertito = @iconv($charset, 'UTF-8//IGNORE', $testo);
                    $testo = $convertito === false ? $testo : $convertito;
                }

                return $testo === false ? $m[0] : $testo;
            },
            $value
        );

        return self::senzaVirgolette($decoded ?? $value);
    }

    /** "\"Booking.com\"" → "Booking.com" (il nome del mittente arriva con le virgolette). */
    private static function senzaVirgolette(string $value): string
    {
        $value = trim($value);

        return strlen($value) >= 2 && $value[0] === '"' && str_ends_with($value, '"')
            ? trim(substr($value, 1, -1))
            : $value;
    }
}
