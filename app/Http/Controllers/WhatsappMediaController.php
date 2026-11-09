<?php

namespace App\Http\Controllers;

use App\Models\WhatsappMessage;
use App\Models\WhatsappSession;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Allegati WhatsApp (foto, sticker, vocali, video, documenti) scaricati da FusionWA al momento.
 * Foto, audio, video e PDF si aprono nella pagina; gli altri documenti si scaricano soltanto.
 */
class WhatsappMediaController extends Controller
{
    private const MAX_BYTES = 25 * 1024 * 1024;

    /** Tipi accettati per ciascun media WhatsApp: niente HTML/SVG/script serviti dal nostro dominio. */
    private const MIME = [
        'image' => ['image/jpeg', 'image/png', 'image/webp'],
        'sticker' => ['image/webp', 'image/png'],
        'audio' => ['audio/ogg', 'audio/mpeg', 'audio/mp4', 'audio/aac', 'audio/amr', 'audio/opus', 'audio/webm'],
        'video' => ['video/mp4', 'video/3gpp', 'video/quicktime', 'video/webm'],
    ];

    public function __invoke(WhatsappMessage $message)
    {
        $tenantId = TenantContext::id();
        abort_unless($tenantId && (int) $message->tenant_id === $tenantId, 404);
        abort_unless(in_array($message->media_type, WhatsappMessage::MEDIA_APRIBILI, true) && $message->media_id, 404);
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
                    if ($total > self::MAX_BYTES || $downloaded > self::MAX_BYTES) {
                        throw new \RuntimeException('Media exceeds size limit');
                    }
                },
            ])->connectTimeout(5)->timeout(60)->get($base.'/api/v1/media/'.rawurlencode($message->media_id), [
                'externalCustomerId' => (string) $tenantId,
            ]);
        } catch (\Throwable) {
            abort(502, 'Allegato temporaneamente non disponibile.');
        }
        abort_unless($response->successful(), $response->status() === 404 ? 404 : 502, 'Allegato non disponibile.');

        $mime = Str::lower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        $bytes = $response->body();
        abort_if(strlen($bytes) > self::MAX_BYTES, 413);

        $headers = [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ];

        if ($message->media_type === 'document') {
            // PDF anteprima nel browser; tutto il resto solo download, con un tipo che il browser non esegue.
            $pdf = $mime === 'application/pdf' && str_starts_with($bytes, '%PDF');
            $nome = $this->nomeFile($message, $pdf ? 'pdf' : null);

            // Chrome non mostra i PDF in una pagina "sandbox": per loro niente CSP (restano nosniff e tipo controllato).
            if ($pdf) {
                unset($headers['Content-Security-Policy']);
            }

            return response($bytes, 200, $headers + [
                'Content-Type' => $pdf ? 'application/pdf' : 'application/octet-stream',
                'Content-Disposition' => ($pdf ? 'inline' : 'attachment').'; filename="'.Str::ascii($nome).'"; filename*=UTF-8\'\''.rawurlencode($nome),
            ]);
        }

        abort_unless(in_array($mime, self::MIME[$message->media_type], true), 415);
        if (in_array($message->media_type, ['image', 'sticker'], true)) {
            $info = @getimagesizefromstring($bytes);
            abort_unless($info && ($info['mime'] ?? null) === $mime, 415);
        }

        return response($bytes, 200, $headers + [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline',
        ]);
    }

    private function nomeFile(WhatsappMessage $message, ?string $estensione): string
    {
        $nome = $message->media_filename ?: 'documento-whatsapp-'.$message->id;
        $nome = preg_replace('/[\x00-\x1F"\\\\\/]/u', '', $nome) ?: 'documento';
        if ($estensione && ! Str::endsWith(Str::lower($nome), '.'.$estensione)) {
            $nome .= '.'.$estensione;
        }

        return $nome;
    }
}
