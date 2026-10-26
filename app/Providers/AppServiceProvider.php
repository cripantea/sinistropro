<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StripeClient::class, fn () => new StripeClient(array_filter(['api_key' => config('services.stripe.secret')])));
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
