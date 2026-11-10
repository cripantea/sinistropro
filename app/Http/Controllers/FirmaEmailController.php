<?php

namespace App\Http\Controllers;

use App\Models\EmailFirma;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lista delle firme email: ognuno ha le sue, l'amministratore gestisce quelle di tutta l'agenzia
 * e sceglie quella proposta di base a tutti (es. "Generale").
 */
class FirmaEmailController extends Controller
{
    public static function elenco(): array
    {
        $user = auth()->user();
        $firme = EmailFirma::disponibiliPer($user)->orderByRaw('user_id is null')->orderBy('nome')->get();
        $agenzia = self::predefinitaAgenzia();

        return [
            'firme' => $firme->map(fn (EmailFirma $f) => [
                'id' => $f->id,
                'nome' => $f->nome,
                'testo' => (string) $f->testo,
                'immagine' => $f->urlImmagine(),
                'agenzia' => $f->user_id === null,
                'modificabile' => $f->modificabileDa($user),
            ])->all(),
            // La scelta personale vince; altrimenti quella decisa dall'amministratore per tutti.
            'preferita' => $user->firma_email_id ?? ($firme->contains('id', $agenzia) ? $agenzia : null),
            'predefinitaAgenzia' => $agenzia,
            'puoAgenzia' => EmailFirma::gestisceAgenzia($user),
        ];
    }

    public static function predefinitaAgenzia(?Tenant $tenant = null): ?int
    {
        $id = ($tenant ?? TenantContext::tenant())?->getSetting('firma_email_predefinita');

        return $id ? (int) $id : null;
    }

    public function store(Request $request): JsonResponse
    {
        $dati = $this->valida($request, null);
        abort_if($dati['agenzia'] && ! EmailFirma::gestisceAgenzia($request->user()), 403, 'Solo l\'amministratore crea firme per tutta l\'agenzia.');

        $firma = EmailFirma::create([
            'tenant_id' => TenantContext::id(),
            'user_id' => $dati['agenzia'] ? null : $request->user()->id,
            'nome' => $dati['nome'],
            'testo' => $dati['testo'] ?? null,
        ]);
        $this->salvaImmagine($request, $firma);
        if ($dati['preferita'] ?? false) {
            $request->user()->forceFill(['firma_email_id' => $firma->id])->save();
        }

        return response()->json(self::elenco() + ['creata' => $firma->id]);
    }

    public function update(Request $request, EmailFirma $firma): JsonResponse
    {
        abort_unless($firma->modificabileDa($request->user()), 403, 'Non puoi modificare questa firma.');
        $dati = $this->valida($request, $firma);
        $firma->update(['nome' => $dati['nome'], 'testo' => $dati['testo'] ?? null]);
        if ($request->boolean('togli_immagine') && $firma->immagine) {
            Storage::disk('s3')->delete($firma->immagine);
            $firma->update(['immagine' => null, 'immagine_mime' => null]);
        }
        $this->salvaImmagine($request, $firma);

        return response()->json(self::elenco());
    }

    public function destroy(Request $request, EmailFirma $firma): JsonResponse
    {
        abort_unless($firma->modificabileDa($request->user()), 403, 'Non puoi eliminare questa firma.');
        if ($firma->immagine) {
            Storage::disk('s3')->delete($firma->immagine);
        }
        $firma->delete();

        return response()->json(self::elenco());
    }

    /** Firma proposta di solito quando si scrive (vuoto = nessuna). */
    public function preferita(Request $request): JsonResponse
    {
        $id = $request->input('firma_id');
        abort_if($id && ! EmailFirma::disponibiliPer($request->user())->whereKey($id)->exists(), 404);
        $request->user()->forceFill(['firma_email_id' => $id ?: null])->save();

        return response()->json(self::elenco());
    }

    /** L'amministratore sceglie la firma di agenzia proposta di base a tutti (anche alle automazioni). */
    public function predefinita(Request $request): JsonResponse
    {
        abort_unless(EmailFirma::gestisceAgenzia($request->user()), 403);
        $id = $request->input('firma_id');
        abort_if($id && ! EmailFirma::whereNull('user_id')->whereKey($id)->exists(), 404);
        $tenant = TenantContext::tenant();
        $tenant->update(['settings' => [...($tenant->settings ?? []), 'firma_email_predefinita' => $id ? (int) $id : null]]);

        return response()->json(self::elenco());
    }

    public function immagine(EmailFirma $firma): Response
    {
        abort_unless($firma->immagine, 404);

        return response($firma->contenutoImmagine(), 200, [
            'Content-Type' => $firma->immagine_mime ?: 'image/png',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    private function salvaImmagine(Request $request, EmailFirma $firma): void
    {
        $file = $request->file('immagine');
        if (! $file) {
            return;
        }
        if ($firma->immagine) {
            Storage::disk('s3')->delete($firma->immagine);
        }
        $chiave = sprintf('tenant_%d/firme/%s.%s', $firma->tenant_id, Str::uuid(), $file->extension() ?: 'png');
        Storage::disk('s3')->put($chiave, $file->getContent(), 'private');
        $firma->update(['immagine' => $chiave, 'immagine_mime' => $file->getMimeType()]);
    }

    /** Errori in JSON (422) anche fuori da /api: la finestra delle firme li mostra sotto i campi. */
    private function valida(Request $request, ?EmailFirma $firma): array
    {
        $haImmagine = $request->hasFile('immagine') || ($firma?->immagine && ! $request->boolean('togli_immagine'));
        $validator = Validator::make($request->all(), [
            'nome' => ['required', 'string', 'max:80'],
            'testo' => [$haImmagine ? 'nullable' : 'required', 'nullable', 'string', 'max:2000'],
            'immagine' => ['nullable', 'file', 'mimes:png,jpg,jpeg,gif', 'max:1024'],
            'agenzia' => ['nullable', 'boolean'],
            'preferita' => ['nullable', 'boolean'],
        ], [
            'nome.required' => 'Dai un nome alla firma (es. "Generale").',
            'testo.required' => 'Scrivi il testo della firma o carica un\'immagine.',
            'immagine.mimes' => 'L\'immagine deve essere PNG, JPG o GIF.',
            'immagine.max' => 'L\'immagine può pesare al massimo 1 MB.',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422));
        }

        return ['agenzia' => $request->boolean('agenzia'), 'preferita' => $request->boolean('preferita')] + $validator->validated();
    }
}
