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
}
