<?php

namespace App\Support;

/** Numeri per WhatsApp: solo cifre col prefisso internazionale (i cellulari italiani spesso arrivano senza 39). */
class NumeroWhatsapp
{
    public static function normalizza(?string $numero): ?string
    {
        $raw = trim((string) $numero);
        if ($raw === '') {
            return null;
        }
        $cifre = preg_replace('/\D/', '', $raw);
        if (str_starts_with($raw, '00')) {
            $cifre = substr($cifre, 2);
        } elseif (! str_starts_with($raw, '+') && strlen($cifre) <= 10 && ! str_starts_with($cifre, '39')) {
            $cifre = '39'.$cifre;
        }

        return strlen($cifre) >= 8 ? $cifre : null;
    }
}
