<?php

namespace App\FusionKit;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Http;

/** Widget Assistenza, pulsanti di login ed errori verso Fusion Control. */
class Support
{
    /** Dati per la pagina di login (prop Inertia "fusion"). */
    public static function loginProps(): array
    {
        return [
            'enabled' => Oidc::enabled(),
            'google' => Oidc::enabled() && (bool) config('fusionkit.google_idp_id'),
            'legacy' => Oidc::legacyLoginEnabled(),
            'error' => session('fusion_error'),
        ];
    }

    /** Tag <script> del widget Assistenza (da mettere in app.blade.php). */
    public static function widgetTag(?Authenticatable $user, array $options = []): string
    {
        $control = (string) config('fusionkit.control_url');
        if (! $user || $control === '' || ! config('fusionkit.kit_secret') || ! config('fusionkit.product')) {
            return '';
        }
        $who = app(Adapter::class)->widgetUser($user);
        if (! $who) {
            return '';
        }
        $token = KitToken::sign(['product' => config('fusionkit.product')] + $who, 12 * 3600);
        $attrs = [
            'src' => $control.'/fusion-kit/widget.js',
            'data-product' => config('fusionkit.product'),
            'data-token' => $token,
            'data-desk' => $control,
            'data-position' => $options['position'] ?? 'right',
            'data-button' => ($options['button'] ?? true) ? 'on' : 'off',
            'data-offset' => (string) ($options['offset'] ?? 0),
            'data-release' => (string) (env('FUSION_RELEASE') ?: env('GIT_COMMIT') ?: ''),
        ];

        return '<script defer '.collect($attrs)->map(fn ($v, $k) => $k.'="'.e($v).'"')->implode(' ').'></script>';
    }

    /** Errore del server → Fusion Control. Non lancia mai. */
    public static function reportError(\Throwable $e): void
    {
        $control = (string) config('fusionkit.control_url');
        if ($control === '' || ! config('fusionkit.kit_secret') || ! config('fusionkit.product')) {
            return;
        }
        try {
            $request = request();
            Http::timeout(3)
                ->withToken((string) config('fusionkit.kit_secret'))
                ->withHeaders(['X-Fusion-Product' => (string) config('fusionkit.product')])
                ->post($control.'/api/fusion/errors', [
                    'source' => 'server',
                    'message' => mb_substr(get_class($e).': '.$e->getMessage(), 0, 2000),
                    'stack' => mb_substr($e->getFile().':'.$e->getLine()."\n".$e->getTraceAsString(), 0, 8000),
                    'path' => $request?->path() ? '/'.ltrim($request->path(), '/') : null,
                    'method' => $request?->method(),
                    'userEmail' => $request?->user()?->email,
                    'release' => env('FUSION_RELEASE') ?: env('GIT_COMMIT'),
                ]);
        } catch (\Throwable) {
            // Control irraggiungibile: l'errore resta nei log del prodotto.
        }
    }
}
