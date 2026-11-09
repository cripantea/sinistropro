<?php

namespace App\Http\Controllers;

use App\Jobs\SyncTenantMailboxJob;
use App\Models\Tenant;
use App\Models\TenantMailSettings;
use App\Models\User;
use App\Services\EmailConnectionTester;
use App\Services\EmailProviders;
use App\Services\MicrosoftMail;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * L'agenzia collega da sola la sua casella (lettura + invio): scrive email e password,
 * il provider si riconosce da solo, la connessione si prova prima di salvare.
 * Solo l'amministratore dell'agenzia o il superadmin dentro l'agenzia.
 */
class ImpostazioniEmailController extends Controller
{
    public function show(): Response
    {
        $tenant = $this->tenant();
        $s = $tenant->mailSettings;
        $collegata = (bool) ($s && $s->is_active && $s->host && $s->imap_host);

        return Inertia::render('Impostazioni/Email', [
            'collegata' => $collegata ? [
                'email' => $s->from_address,
                'nome' => $s->from_name,
                'username' => $s->username,
                'host' => $s->host, 'port' => $s->port, 'encryption' => $s->encryption,
                'imap_host' => $s->imap_host, 'imap_port' => $s->imap_port, 'imap_encryption' => $s->imap_encryption,
                'microsoft' => $s->isMicrosoft(),
            ] : null,
            'nomeAgenzia' => $tenant->name,
            'microsoftDisponibile' => MicrosoftMail::configured(),
        ]);
    }

    public function rileva(Request $request, EmailProviders $providers): JsonResponse
    {
        // Anche al superadmin dalla scheda tenant, senza essere dentro un'agenzia.
        abort_unless(self::puoCollegare(), 403);
        $email = $request->validate(['email' => ['required', 'email']])['email'];

        return response()->json(['provider' => $providers->detect($email)]);
    }

    public function store(Request $request, EmailProviders $providers, EmailConnectionTester $tester): RedirectResponse
    {
        $tenant = $this->tenant();

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'nome' => ['nullable', 'string', 'max:255'],
            'avanzate' => ['boolean'],
            'username' => ['nullable', 'string', 'max:255'],
            'host' => ['nullable', 'required_if:avanzate,true', 'string', 'max:255'],
            'port' => ['nullable', 'required_if:avanzate,true', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['nullable', Rule::in(['tls', 'ssl'])],
            'imap_host' => ['nullable', 'required_if:avanzate,true', 'string', 'max:255'],
            'imap_port' => ['nullable', 'required_if:avanzate,true', 'integer', 'min:1', 'max:65535'],
            'imap_encryption' => ['nullable', Rule::in(['tls', 'ssl'])],
        ]);

        $email = Str::lower(trim($data['email']));
        $settings = $tenant->mailSettings ?? new TenantMailSettings(['tenant_id' => $tenant->id]);
        $stessaCasella = $settings->exists && Str::lower((string) $settings->from_address) === $email;

        // Password vuota = tiene quella salvata, ma solo se è la stessa casella.
        $password = filled($data['password'] ?? null)
            ? $this->pulisciPassword($data['password'])
            : ($stessaCasella ? $settings->password : null);

        if (! $password) {
            throw ValidationException::withMessages(['password' => 'Scrivi la password della casella.']);
        }

        $provider = null;
        if (! empty($data['avanzate'])) {
            $config = [
                'username' => ($data['username'] ?? null) ?: $email,
                'host' => $data['host'], 'port' => (int) $data['port'], 'encryption' => $data['encryption'] ?? null,
                'imap_host' => $data['imap_host'], 'imap_port' => (int) $data['imap_port'], 'imap_encryption' => $data['imap_encryption'] ?? 'ssl',
            ];
        } else {
            $provider = $providers->detect($email);
            if (! $provider) {
                throw ValidationException::withMessages(['email' => 'Non riconosco il provider di questa email: apri "Impostazioni avanzate" e scrivi i server (te li dà chi gestisce la posta).']);
            }
            if (! $provider['supportato']) {
                throw ValidationException::withMessages(['email' => $provider['nota']]);
            }
            $config = [
                'username' => $provider['username'],
                'host' => $provider['smtp']['host'], 'port' => $provider['smtp']['port'], 'encryption' => $provider['smtp']['encryption'],
                'imap_host' => $provider['imap']['host'], 'imap_port' => $provider['imap']['port'], 'imap_encryption' => $provider['imap']['encryption'],
            ];
        }

        if ($errore = $tester->test($config + ['password' => $password], $provider['chiave'] ?? null)) {
            throw ValidationException::withMessages(['password' => $errore]);
        }

        // Casella nuova: si riparte dagli ultimi 7 giorni (gli UID valgono solo nella casella vecchia).
        if (! $stessaCasella || $settings->imap_host !== $config['imap_host']) {
            $settings->imap_last_uid_inbox = null;
            $settings->imap_last_uid_sent = null;
        }

        $settings->fill([
            'host' => $config['host'],
            'port' => $config['port'],
            'encryption' => $config['encryption'],
            'username' => $config['username'],
            'from_address' => $email,
            'from_name' => ($data['nome'] ?? null) ?: $tenant->name,
            'is_active' => true,
            'imap_host' => $config['imap_host'],
            'imap_port' => $config['imap_port'],
            'imap_encryption' => $config['imap_encryption'],
        ]);
        $settings->password = $password;
        $settings->forceFill(['auth_type' => 'password', 'oauth_refresh_token' => null, 'oauth_access_token' => null, 'oauth_expires_at' => null]);
        $settings->tenant_id = $tenant->id;
        $settings->save();

        SyncTenantMailboxJob::dispatch($tenant->id);

        return redirect()->route('impostazioni.email')
            ->with('success', "Casella {$email} collegata: le email arrivano in SinistroPro entro un paio di minuti.");
    }

    public function destroy(): RedirectResponse
    {
        $tenant = $this->tenant();

        $tenant->mailSettings?->update([
            'is_active' => false,
            'password' => null,
            'oauth_refresh_token' => null,
            'oauth_access_token' => null,
            'oauth_expires_at' => null,
            'imap_last_uid_inbox' => null,
            'imap_last_uid_sent' => null,
        ]);

        return redirect()->route('impostazioni.email')->with('success', 'Casella scollegata.');
    }

    /** Va su Microsoft per il permesso; torna su microsoftCallback. */
    public function microsoft(Request $request, MicrosoftMail $microsoft): RedirectResponse
    {
        $tenant = $this->tenant();
        abort_unless(MicrosoftMail::configured(), 404);

        $state = Str::random(40);
        $request->session()->put('microsoft_mail', ['state' => $state, 'tenant_id' => $tenant->id, 'nome' => $request->string('nome')->limit(255)->toString()]);

        $email = $request->string('email')->toString();

        return redirect()->away($microsoft->authorizeUrl($state, filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null));
    }

    public function microsoftCallback(Request $request, MicrosoftMail $microsoft, EmailConnectionTester $tester): RedirectResponse
    {
        $tenant = $this->tenant();
        $attesa = $request->session()->pull('microsoft_mail');
        $indietro = redirect()->route('impostazioni.email');

        if (! $attesa || ! hash_equals($attesa['state'], (string) $request->query('state')) || $attesa['tenant_id'] !== $tenant->id) {
            return $indietro->with('error', 'Collegamento Microsoft scaduto: riprova dal pulsante.');
        }

        if ($request->filled('error')) {
            $motivo = (string) $request->query('error_description', $request->query('error'));

            return $indietro->with('error', Str::contains($motivo, ['AADSTS65001', 'AADSTS90094', 'admin', 'amministratore'])
                ? 'Serve il consenso dell\'amministratore di Microsoft 365: deve fare lui l\'accesso con questo pulsante (e spuntare "Consenso per conto dell\'organizzazione"), oppure approvare SinistroPro dal portale.'
                : ($request->query('error') === 'access_denied' ? 'Accesso Microsoft annullato.' : 'Microsoft ha rifiutato: '.Str::limit($motivo, 250)));
        }

        try {
            $token = $microsoft->exchangeCode((string) $request->query('code'));
        } catch (\Throwable $e) {
            return $indietro->with('error', $e->getMessage());
        }

        $config = [
            'username' => $token['email'],
            'password' => $token['access_token'],
            'oauth' => true,
            'host' => MicrosoftMail::SMTP['host'], 'port' => MicrosoftMail::SMTP['port'], 'encryption' => MicrosoftMail::SMTP['encryption'],
            'imap_host' => MicrosoftMail::IMAP['host'], 'imap_port' => MicrosoftMail::IMAP['port'], 'imap_encryption' => MicrosoftMail::IMAP['encryption'],
        ];

        // Senza lettura non si salva; se manca solo l'invio (SMTP autenticato spento) si salva e lo si dice.
        try {
            $tester->imap($config);
        } catch (\Throwable $e) {
            return $indietro->with('error', $tester->spiega('lettura (IMAP)', $e, 'microsoft'));
        }
        $avvisoInvio = null;
        try {
            $tester->smtp($config);
        } catch (\Throwable $e) {
            $avvisoInvio = $tester->spiega('invio (SMTP)', $e, 'microsoft');
        }

        $settings = $tenant->mailSettings ?? new TenantMailSettings(['tenant_id' => $tenant->id]);
        if (Str::lower((string) $settings->from_address) !== $token['email'] || $settings->imap_host !== $config['imap_host']) {
            $settings->imap_last_uid_inbox = null;
            $settings->imap_last_uid_sent = null;
        }

        $settings->fill([
            'host' => $config['host'], 'port' => $config['port'], 'encryption' => $config['encryption'],
            'imap_host' => $config['imap_host'], 'imap_port' => $config['imap_port'], 'imap_encryption' => $config['imap_encryption'],
            'username' => $token['email'],
            'from_address' => $token['email'],
            'from_name' => ($attesa['nome'] ?? '') ?: ($settings->from_name ?: $tenant->name),
            'is_active' => true,
        ]);
        $settings->forceFill([
            'password' => null,
            'auth_type' => 'microsoft',
            'oauth_refresh_token' => $token['refresh_token'],
            'oauth_access_token' => $token['access_token'],
            'oauth_expires_at' => $token['expires_at'],
        ]);
        $settings->tenant_id = $tenant->id;
        $settings->save();

        SyncTenantMailboxJob::dispatch($tenant->id);

        $indietro->with('success', "Casella {$token['email']} collegata con Microsoft: le email arrivano in SinistroPro entro un paio di minuti.");

        return $avvisoInvio ? $indietro->with('error', 'Attenzione, l\'invio non funziona ancora. '.$avvisoInvio) : $indietro;
    }

    /** La password per le app di Google si copia come "abcd efgh ijkl mnop": gli spazi vanno tolti. */
    private function pulisciPassword(string $password): string
    {
        return preg_match('/^\s*[a-z]{4}( [a-z]{4}){3}\s*$/', $password)
            ? str_replace(' ', '', trim($password))
            : $password;
    }

    /**
     * Admin dell'agenzia, superadmin, oppure superadmin in assistenza "come" un utente qualsiasi
     * (impersonazione: l'utente loggato è il collaboratore, ma chi agisce è il superadmin).
     */
    public static function puoCollegare(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }
        if ($user->isTenantAdmin() || $user->role === 'superadmin') {
            return true;
        }
        $impersonatore = session('impersonator_id');

        return $impersonatore && User::whereKey($impersonatore)->where('role', 'superadmin')->exists();
    }

    private function tenant(): Tenant
    {
        abort_unless(self::puoCollegare(), 403, 'Solo l\'amministratore dell\'agenzia può collegare la casella email.');

        $tenant = TenantContext::tenant();
        abort_unless($tenant, 404);

        return $tenant;
    }
}
