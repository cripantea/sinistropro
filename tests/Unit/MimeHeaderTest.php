<?php

use App\Support\MimeHeader;

// Oggetti e nomi delle email: in produzione manca l'estensione imap e arrivavano come "=?UTF-8?B?...?=".
test('decodifica oggetti e nomi codificati senza rovinare gli accenti normali', function (string $grezzo, string $atteso) {
    expect(MimeHeader::decode($grezzo))->toBe($atteso);
})->with([
    ['=?UTF-8?B?THVjYSwgTGUgYWRvcmVyYWkuIENvbnNlZ25hIGdyYXR1aXRhLg==?=', 'Luca, Le adorerai. Consegna gratuita.'],
    ['=?utf-8?B?Q2hvb3NlIHRoZSBBZG1pbmlzdGVyZWQgUmVnaW1lOiBqb2luaW5n?= =?utf-8?B?IGlzIGZyZWUhIPCfk53inIU=?=', 'Choose the Administered Regime: joining is free! 📝✅'],
    ['Città: adesivi a 18 =?UTF-8?B?4oKs?=', 'Città: adesivi a 18 €'],
    ['=?UTF-8?Q?Hai_1_nuova_opportunit=C3=A0_di_lavoro!?=', 'Hai 1 nuova opportunità di lavoro!'],
    ['=?ISO-8859-1?Q?caff=E8?=', 'caffè'],
    ['Testo normale è', 'Testo normale è'],
    ['"Booking.com"', 'Booking.com'],
    ['=?UTF-8?b?4pmfQ2hlc3MuY29t?=', '♟Chess.com'],
]);
