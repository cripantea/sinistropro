<?php

namespace App\FusionKit;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider;

/**
 * Fusion Kit: route /auth/fusion/* e /api/fusion/*, comando fusion:export-users,
 * errori inviati a Fusion Control. Si registra in bootstrap/providers.php.
 * Il prodotto fornisce App\FusionKitAdapter (implementa Adapter).
 */
class FusionKitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(config_path('fusionkit.php'), 'fusionkit');
        $this->app->bind(Adapter::class, \App\FusionKitAdapter::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/fusionkit.php'));

        if ($this->app->runningInConsole()) {
            $this->commands([ExportUsersCommand::class]);
        }

        $handler = $this->app->make(ExceptionHandler::class);
        if (method_exists($handler, 'reportable')) {
            $handler->reportable(function (\Throwable $e) {
                Support::reportError($e);
            });
        }
    }
}
