<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Mail\TenantMailTestMail;
use App\Models\Tenant;
use App\Models\TenantMailSettings;
use App\Services\TenantMailerResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Webklex\PHPIMAP\ClientManager;

class TenantMailSettingsController extends Controller
{
    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'encryption' => ['nullable', Rule::in(['tls', 'ssl'])],
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'imap_host' => ['nullable', 'string', 'max:255'],
            'imap_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'imap_encryption' => ['nullable', Rule::in(['tls', 'ssl'])],
        ]);

        $settings = $tenant->mailSettings ?? new TenantMailSettings(['tenant_id' => $tenant->id]);

        $settings->fill([
            'host' => $data['host'] ?? null,
            'port' => $data['port'] ?? null,
            'username' => $data['username'] ?? null,
            'encryption' => $data['encryption'] ?? null,
            'from_address' => $data['from_address'] ?? null,
            'from_name' => $data['from_name'] ?? null,
            'is_active' => $data['is_active'] ?? false,
            'imap_host' => $data['imap_host'] ?? null,
            'imap_port' => $data['imap_port'] ?? null,
            'imap_encryption' => $data['imap_encryption'] ?? null,
        ]);

        // La password non torna mai al frontend: si aggiorna solo se ne viene
        // inviata una nuova, altrimenti resta quella già salvata (cifrata).
        if (! empty($data['password'])) {
            $settings->password = $data['password'];
        }

        $settings->tenant_id = $tenant->id;
        $settings->save();

        return redirect()
            ->to(route('superadmin.tenants.edit', $tenant).'?tab=email')
            ->with('success', 'Configurazione email salvata.');
    }

    public function test(Request $request, Tenant $tenant, TenantMailerResolver $resolver): JsonResponse
    {
        $data = $request->validate([
            'to_email' => ['required', 'email'],
        ]);

        $settings = $tenant->mailSettings;

        if (! $settings || ! $settings->host) {
            return response()->json([
                'message' => 'Salva prima la configurazione: non c\'è ancora nulla da testare.',
            ], 422);
        }

        try {
            $resolver->sendTestWith([
                'host' => $settings->host,
                'port' => $settings->port,
                'encryption' => $settings->encryption,
                'username' => $settings->username,
                'password' => $settings->password,
                'from_address' => $settings->from_address,
                'from_name' => $settings->from_name,
            ], $data['to_email'], new TenantMailTestMail($tenant));

            return response()->json(['message' => "Email di prova inviata a {$data['to_email']}."]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Invio fallito: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Verifica la connessione IMAP con le credenziali già salvate, prima di
     * affidarsi al poller silenzioso di sync (ogni 2 minuti in produzione).
     */
    public function testImap(Tenant $tenant): JsonResponse
    {
        $settings = $tenant->mailSettings;

        if (! $settings || ! $settings->imap_host) {
            return response()->json([
                'message' => 'Salva prima host/porta IMAP: non c\'è ancora nulla da testare.',
            ], 422);
        }

        try {
            $client = (new ClientManager())->make([
                'host' => $settings->imap_host,
                'port' => $settings->imap_port ?: 993,
                'encryption' => $settings->imap_encryption ?: 'ssl',
                'validate_cert' => true,
                'username' => $settings->username,
                'password' => $settings->password,
            ]);

            $client->connect();
            $client->disconnect();

            return response()->json(['message' => 'Connessione IMAP riuscita.']);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Connessione fallita: '.$e->getMessage(),
            ], 422);
        }
    }
}
