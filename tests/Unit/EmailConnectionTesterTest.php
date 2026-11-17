<?php

use App\Services\EmailConnectionTester;
use Symfony\Component\Mailer\Exception\TransportException;

// La prova SMTP vera (non finta): un server che non risponde deve dare un errore di connessione,
// non un errore di PHP. Il 17/11/2026 un setTimeout inesistente faceva fallire ogni collegamento
// con "il server non risponde".
test('la prova SMTP arriva davvero alla connessione', function () {
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $porta = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket); // porta libera e chiusa: connessione rifiutata subito

    expect(fn () => (new EmailConnectionTester())->smtp([
        'host' => '127.0.0.1', 'port' => $porta, 'encryption' => 'tls',
        'username' => 'prova@gmail.com', 'password' => 'x',
    ]))->toThrow(TransportException::class);
});
