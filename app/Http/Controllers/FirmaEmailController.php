<?php

namespace App\Http\Controllers;

use App\Models\EmailFirma;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** Lista delle firme email: ognuno ha le sue, l'amministratore gestisce anche quelle di tutta l'agenzia. */
class FirmaEmailController extends Controller
{
    public static function elenco(): array
    {
        $user = auth()->user();

        return [
            'firme' => EmailFirma::disponibiliPer($user)->orderByRaw('user_id is null')->orderBy('nome')->get()
                ->map(fn (EmailFirma $f) => [
                    'id' => $f->id,
                    'nome' => $f->nome,
                    'testo' => $f->testo,
                    'agenzia' => $f->user_id === null,
                    'modificabile' => $f->modificabileDa($user),
                ])->all(),
            'preferita' => $user->firma_email_id,
            'puoAgenzia' => EmailFirma::gestisceAgenzia($user),
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $dati = $this->valida($request);
        abort_if($dati['agenzia'] && ! EmailFirma::gestisceAgenzia($request->user()), 403, 'Solo l\'amministratore crea firme per tutta l\'agenzia.');

        $firma = EmailFirma::create([
            'tenant_id' => TenantContext::id(),
            'user_id' => $dati['agenzia'] ? null : $request->user()->id,
            'nome' => $dati['nome'],
            'testo' => $dati['testo'],
        ]);
        if ($dati['preferita'] ?? false) {
            $request->user()->forceFill(['firma_email_id' => $firma->id])->save();
        }

        return response()->json(self::elenco() + ['creata' => $firma->id]);
    }

    public function update(Request $request, EmailFirma $firma): JsonResponse
    {
        abort_unless($firma->modificabileDa($request->user()), 403, 'Non puoi modificare questa firma.');
        $dati = $this->valida($request);
        $firma->update(['nome' => $dati['nome'], 'testo' => $dati['testo']]);

        return response()->json(self::elenco());
    }

    public function destroy(Request $request, EmailFirma $firma): JsonResponse
    {
        abort_unless($firma->modificabileDa($request->user()), 403, 'Non puoi eliminare questa firma.');
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

    /** Errori in JSON (422) anche fuori da /api: la finestra delle firme li mostra sotto i campi. */
    private function valida(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'nome' => ['required', 'string', 'max:80'],
            'testo' => ['required', 'string', 'max:2000'],
            'agenzia' => ['boolean'],
            'preferita' => ['boolean'],
        ], [
            'nome.required' => 'Dai un nome alla firma (es. "Ufficio sinistri").',
            'testo.required' => 'Scrivi il testo della firma.',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422));
        }

        return $validator->validated() + ['agenzia' => false];
    }
}
