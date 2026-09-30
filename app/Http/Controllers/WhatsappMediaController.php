<?php

namespace App\Http\Controllers;

use App\Models\WhatsappMessage;
use App\Models\WhatsappSession;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Http;

class WhatsappMediaController extends Controller
{
    public function __invoke(WhatsappMessage $message)
    {
        $tenantId = TenantContext::id();
        abort_unless($tenantId && (int) $message->tenant_id === $tenantId, 404);
        abort_unless($message->media_type === 'image' && $message->media_id, 404);
        $session = WhatsappSession::where('tenant_id', $tenantId)->where('status', 'active')->first();
        // Only FusionWA-managed sessions support this endpoint.
        abort_unless($session && $session->waba_id, 404);
        $base = rtrim((string) config('services.fusionwa.base_url'), '/');
        try {
            $response = Http::withHeaders([
                'x-fusionwa-api-key' => config('services.fusionwa.api_key'),
                'x-fusionwa-api-secret' => config('services.fusionwa.api_secret'),
            ])->withOptions([
                'allow_redirects' => false,
                'progress' => static function ($total, $downloaded) {
                    if ($total > 5 * 1024 * 1024 || $downloaded > 5 * 1024 * 1024) {
                        throw new \RuntimeException('Image exceeds size limit');
                    }
                },
            ])->connectTimeout(5)->timeout(20)->get($base.'/api/v1/media/'.rawurlencode($message->media_id), [
                'externalCustomerId' => (string) $tenantId,
            ]);
        } catch (\Throwable) {
            abort(502, 'Immagine temporaneamente non disponibile.');
        }
        abort_unless($response->successful(), $response->status() === 404 ? 404 : 502, 'Immagine non disponibile.');
        $mime = trim(explode(';', $response->header('Content-Type'))[0]);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true), 415);
        $bytes = $response->body();
        abort_if(strlen($bytes) > 5 * 1024 * 1024, 413);
        $info = @getimagesizefromstring($bytes);
        abort_unless($info && ($info['mime'] ?? null) === $mime, 415);
        return response($bytes, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ]);
    }
}
