<?php

namespace App\Providers;

use App\Events\PraticaStatoAggiornato;
use App\Listeners\EseguiAutomazioniTenant;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // ── Event → Listener wiring ───────────────────────────────────
        Event::listen(
            PraticaStatoAggiornato::class,
            EseguiAutomazioniTenant::class,
        );
    }
}
