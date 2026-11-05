<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Manda a Fusion CRM chi si registra da solo e chi attiva la prova. Il CRM avvisa Luca e, se dopo
 * un po' la prova non è attiva, segna il contatto come "prova non completata" da richiamare.
 * Le chiamate partono dopo la risposta al cliente: un errore del CRM non blocca mai SinistroPro.
 */
class FusionCrm
{
    public const ATTIVITA = [
        'agenzia_assicurativa'  => 'Agenzia assicurativa',
        'broker'                => 'Broker',
        'studio_infortunistica' => 'Studio di infortunistica',
        'studio_legale'         => 'Studio legale',
        'altro'                 => 'Altro',
    ];

    public const SINISTRI = ['meno di 30', '30–100', '100–300', 'più di 300'];

    public static function registrato(User $user): void
    {
        $tenant = $user->tenant;
        [$first, $last] = self::splitName($user->name);

        self::send([
            'externalId'   => "sinistropro-registrazione-{$tenant->id}",
            'firstName'    => $first,
            'lastName'     => $last,
            'email'        => $user->email,
            'phone'        => $tenant->getSetting('telefono_registrazione'),
            'source'       => 'SinistroPro – registrazione prova',
            'tags'         => ['sinistropro', 'prova-registrato'],
            'customFields' => array_filter([
                'attivita'          => self::ATTIVITA[$tenant->getSetting('attivita')] ?? null,
                'pratiche_mese'     => $tenant->getSetting('pratiche_mese'),
                'agenzia_sinistropro' => $tenant->name,
                'creativita'        => self::creativita($tenant->getSetting('attribuzione') ?? []),
            ]),
        ]);
    }

    public static function provaAttiva(Tenant $tenant): void
    {
        $admin = $tenant->users()->where('role', 'tenant-admin')->orderBy('id')->first();
        if (! $admin) {
            return;
        }

        self::send([
            'externalId' => "sinistropro-prova-{$tenant->id}",
            'email'      => $admin->email,
            'phone'      => $tenant->getSetting('telefono_registrazione'),
            'source'     => 'SinistroPro – registrazione prova',
            'tags'       => ['sinistropro', 'prova-attiva'],
        ]);
    }

    private static function send(array $payload): void
    {
        $url = config('services.fusion_crm.webhook_url');
        $token = config('services.fusion_crm.webhook_token');
        if (! $url || ! $token) {
            return;
        }

        dispatch(function () use ($url, $token, $payload) {
            try {
                Http::withHeaders(['X-Fusion-Token' => $token])->timeout(10)->post($url, array_filter($payload, fn ($v) => $v !== null && $v !== []))->throw();
            } catch (\Throwable $e) {
                Log::warning('Fusion CRM: invio non riuscito', ['externalId' => $payload['externalId'], 'error' => $e->getMessage()]);
            }
        })->afterResponse();
    }

    /** Annuncio di provenienza (utm_content, o campagna) per sapere quale creatività porta prove. */
    private static function creativita(array $a): ?string
    {
        return $a['utm_content'] ?? $a['utm_campaign'] ?? (isset($a['fbclid']) ? 'meta (senza utm)' : null);
    }

    private static function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2);

        return [$parts[0] ?? $name, $parts[1] ?? null];
    }
}
