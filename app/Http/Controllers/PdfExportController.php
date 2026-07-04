<?php

namespace App\Http\Controllers;

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
            'utenteCreatore:id,name,email',
            'currentStatus:id,name,color,is_closed',
            'note.user:id,name',
            'allegati',
        ]);

        $pdf = Pdf::loadView('pdf.pratica', ['pratica' => $pratica])
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont'   => 'DejaVu Sans',
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
                'dpi'           => 150,
            ]);

        $filename = sprintf(
            'pratica-%d-%s.pdf',
            $pratica->id,
            now()->format('Y-m-d')
        );

        return $pdf->download($filename);
    }
}
