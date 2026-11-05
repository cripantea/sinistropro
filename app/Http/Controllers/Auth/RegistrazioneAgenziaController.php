<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FusionCrm;
use App\Services\MetaConversions;
use App\Services\NuovaAgenzia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Registrazione da soli (/registrati): nome, agenzia, email e password. Crea l'agenzia già
 * pronta (NuovaAgenzia) con l'amministratore, entra e va a inserire la carta per la prova
 * gratuita. Finché non c'è un abbonamento l'agenzia è in sola lettura (EnsureTenantHasAccess).
 */
class RegistrazioneAgenziaController extends Controller
{
    public function create(Request $request): Response
    {
        // Da quale annuncio arriva (fbclid e utm passati dalla landing): si salva con la registrazione.
        $arrivo = array_filter($request->only(['fbclid', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content']), fn ($v) => is_string($v) && $v !== '' && strlen($v) < 300);
        if ($arrivo) {
            $request->session()->put('attribuzione', [...$arrivo, ...(isset($arrivo['fbclid']) ? ['fbclid_at' => (int) (microtime(true) * 1000)] : [])]);
        }

        return Inertia::render('Auth/Registrati', [
            'attivita' => collect(FusionCrm::ATTIVITA)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'sinistri' => FusionCrm::SINISTRI,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Campo nascosto: lo compilano solo i bot.
        abort_if(filled($request->input('sito')), 422);

        $data = $request->validate([
            'nome'     => ['required', 'string', 'max:255'],
            'agenzia'  => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'telefono' => ['required', 'string', 'regex:/^\+?[0-9 .\/-]{6,20}$/'],
            'attivita' => ['required', Rule::in(array_keys(FusionCrm::ATTIVITA))],
            'sinistri' => ['required', Rule::in(FusionCrm::SINISTRI)],
            'password' => ['required', Password::min(8)],
        ], [
            'email.unique' => 'Esiste già un account con questa email: accedi o recupera la password.',
        ], [
            'nome' => 'nome e cognome', 'agenzia' => 'nome dell\'agenzia', 'telefono' => 'cellulare', 'attivita' => 'tipo di attività', 'sinistri' => 'sinistri gestiti',
        ]);

        $user = NuovaAgenzia::crea($data['agenzia'], $data['nome'], $data['email'], $data['password']);
        // Solo chi si registra da solo ha la prova gratuita (Tenant::trialDays).
        $user->tenant->forceFill(['settings' => [...$user->tenant->settings, 'registrazione_da_solo' => true, 'telefono_registrazione' => trim($data['telefono']),
            'attivita' => $data['attivita'], 'pratiche_mese' => $data['sinistri'],
            'attribuzione' => $request->session()->pull('attribuzione'),
        ]])->save();
        $user = $user->fresh();
        FusionCrm::registrato($user);

        $eventId = "sp-registrazione-{$user->tenant_id}";
        MetaConversions::send($request, 'CompleteRegistration', $eventId, $user, ['content_name' => 'prova_sinistropro', 'attivita' => $data['attivita'], 'sinistri_mese' => $data['sinistri']]);
        $request->session()->flash('traccia', ['event' => 'CompleteRegistration', 'eventID' => $eventId, 'params' => ['content_name' => 'prova_sinistropro']]);

        Log::info('Registrazione: nuova agenzia', ['tenant' => $user->tenant_id, 'email' => $user->email]);

        Auth::login($user);
        $request->session()->regenerate();

        return $user->tenant->trialDays() > 0
            ? redirect()->route('registrati.carta')
            : redirect()->route('abbonamento')->with('success', 'Account creato. Scegli il piano e attiva SinistroPro: dopo il pagamento si sblocca tutto.');
    }

    /** Dopo la prenotazione (o se la salta): il primo sinistro, con la guida. */
    public function primoSinistro(): RedirectResponse
    {
        return redirect()->route('pratiche.create')->with('primi_passi', 'primo_sinistro');
    }

    /**
     * Terzo passo, dopo la carta: prenotare la call di onboarding col tecnico (calendario del CRM
     * incorporato, con nome, email e cellulare già compilati). Si può saltare.
     */
    public function onboarding(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $url = config('billing.onboarding_url');

        if (! $url || ! $user->tenant) {
            return redirect()->route('pratiche.create')->with('primi_passi', 'primo_sinistro');
        }

        $query = http_build_query(array_filter([
            'embed' => 1,
            'name'  => $user->name,
            'email' => $user->email,
            'phone' => $user->tenant->getSetting('telefono_registrazione'),
        ]));

        return Inertia::render('Auth/Registrati', [
            'passo'      => 'onboarding',
            'agenzia'    => $user->tenant->name,
            'bookingUrl' => $url . (str_contains($url, '?') ? '&' : '?') . $query,
            'provaFinoAl' => $user->tenant->subscription_ends_at?->format('d/m/Y'),
        ]);
    }

    /**
     * Non ancora convinto: demo di 20 minuti prenotata dentro SinistroPro, con nome, email e
     * cellulare già compilati (il calendario Demo del CRM, incorporato come quello del tecnico).
     */
    public function demo(Request $request): Response
    {
        $user = $request->user();
        $url = config('billing.demo_url');
        $query = http_build_query(array_filter([
            'embed' => 1,
            'name'  => $user->name,
            'email' => $user->email,
            'phone' => $user->tenant?->getSetting('telefono_registrazione'),
        ]));

        return Inertia::render('Auth/Registrati', [
            'passo'       => 'demo',
            'agenzia'     => $user->tenant?->name,
            'bookingUrl'  => $url . (str_contains($url, '?') ? '&' : '?') . $query,
            'giorniProva' => $user->tenant?->trialDays() ?? 0,
        ]);
    }

    /** Secondo passo della registrazione: la carta per la prova, nella stessa pagina (o sulla pagina Stripe senza chiave pubblica). */
    public function carta(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $tenant = $user->tenant;

        if (! $tenant || ! $user->isTenantAdmin() || $tenant->hasSubscription() || $tenant->trialDays() === 0) {
            return redirect()->route('abbonamento');
        }

        return Inertia::render('Auth/Registrati', [
            'passo'         => 'carta',
            'agenzia'       => $tenant->name,
            'giorniProva'   => $tenant->trialDays(),
            'prezzoMensile' => $tenant->priceCents('month'),
            'stripeKey'     => config('services.stripe.key'),
        ]);
    }
}
