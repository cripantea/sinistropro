<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NuovaAgenzia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Registrazione da soli (/registrati): nome, agenzia, email e password. Crea l'agenzia già
 * pronta (NuovaAgenzia) con l'amministratore, entra e va a pagare. Finché non
 * paga l'agenzia è in sola lettura (EnsureTenantHasAccess).
 */
class RegistrazioneAgenziaController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Registrati');
    }

    public function store(Request $request): RedirectResponse
    {
        // Campo nascosto: lo compilano solo i bot.
        abort_if(filled($request->input('sito')), 422);

        $data = $request->validate([
            'nome'     => ['required', 'string', 'max:255'],
            'agenzia'  => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', Password::min(8)],
        ], [
            'email.unique' => 'Esiste già un account con questa email: accedi o recupera la password.',
        ], [
            'nome' => 'nome e cognome', 'agenzia' => 'nome dell\'agenzia',
        ]);

        $user = NuovaAgenzia::crea($data['agenzia'], $data['nome'], $data['email'], $data['password']);

        Log::info('Registrazione: nuova agenzia', ['tenant' => $user->tenant_id, 'email' => $user->email]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('abbonamento')
            ->with('success', 'Account creato. Scegli il piano e attiva SinistroPro: dopo il pagamento si sblocca tutto.');
    }
}
