<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Lanciata quando si tenta di inviare un'email per un tenant che non ha
 * (ancora) una configurazione SMTP attiva. Nessun fallback silenzioso su un
 * mailer condiviso: l'errore deve emergere subito, non sparire in un invio
 * "di riserva" che nessuno nota.
 */
class MailNotConfiguredException extends RuntimeException {}
