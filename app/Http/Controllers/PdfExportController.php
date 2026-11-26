<?php

namespace App\Http\Controllers;

use App\Models\Contatto;
use App\Models\Pratica;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class PdfExportController extends Controller
{
    /**
     * GET /pratiche/{pratica}/export-pdf
     *
     * Genera e scarica il "Pacchetto" della pratica in formato PDF A4.
     * Il TenantScope sul route model binding impedisce l'accesso cross-tenant.
     */
    public function __invoke(Pratica $pratica): Response
    {
        // Unica query con tutti i dati necessari: zero N+1.
        $pratica->loadMissing([
            'tenant',
            'cliente:id,nome',
            'utenteCreatore:id,name,email',
            'currentStatus:id,name,color,is_closed',
            'note.user:id,name',
            'allegati',
        ]);

        $pdf = Pdf::loadView('pdf.pratica', self::datiVista($pratica))
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont'   => 'DejaVu Sans',
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
                'dpi'           => 150,
            ]);

        // Nel nome file il numero sinistro dell'agenzia (se impostato), altrimenti l'id.
        $numero = preg_replace('/[^A-Za-z0-9._-]+/', '-', ltrim($pratica->numero, '#'));
        $filename = sprintf('sinistro-%s-%s.pdf', trim($numero, '-') ?: $pratica->id, now()->format('Y-m-d'));

        return $pdf->download($filename);
    }

    /** Dati per la vista; la riga del liquidatore solo per le agenzie che l'hanno chiesta (config/agenzie.php). */
    public static function datiVista(Pratica $pratica): array
    {
        $mostra = (bool) config('agenzie.'.$pratica->tenant_id.'.pdf_liquidatore');
        $id = $pratica->contatti['liquidatore'] ?? null;

        return [
            'pratica'           => $pratica,
            'mostraLiquidatore' => $mostra,
            'liquidatore'       => $mostra && $id
                ? Contatto::withoutGlobalScopes()->where('tenant_id', $pratica->tenant_id)->find($id)
                : null,
        ];
    }
}
