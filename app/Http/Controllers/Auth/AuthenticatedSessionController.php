<?php

namespace App\Http\Controllers\Auth;

use App\FusionKit\FusionKitController;
use App\FusionKit\Oidc;
use App\FusionKit\Support;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'fusion' => Support::loginProps(),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        if (! Oidc::legacyLoginEnabled()) {
            throw ValidationException::withMessages(['email' => 'Accedi con Fusion ID.']);
        }

        $request->authenticate();

        if (! Auth::user()->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            throw ValidationException::withMessages([
                'email' => 'Il tuo account è stato disabilitato. Contatta l\'amministratore.',
            ]);
        }

        $request->session()->regenerate();

        if (Auth::user()->role === 'superadmin') {
            return redirect()->route('superadmin.dashboard');
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        // Se la sessione era di Fusion ID si esce anche da lì (tutti i software Fusionsoft).
        $target = FusionKitController::logoutTarget($request);

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return $target === '/' ? redirect('/') : Inertia::location($target);
    }
}
