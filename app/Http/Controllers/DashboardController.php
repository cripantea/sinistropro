<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use App\Models\Pratica;
use App\Models\Tenant;
use App\Support\TenantContext;
use App\Models\TenantStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response|RedirectResponse
    {
        $tenantId = TenantContext::id();

        if (! $tenantId) {
            return redirect()->route('superadmin.dashboard');
        }

        // Conteggi per stato (TenantStatus non ha GlobalScope, filtro manuale)
        $statuses = TenantStatus::where('tenant_id', $tenantId)
            ->orderBy('order')
            ->withCount(['pratiche'])   // subquery scoped via BelongsToTenant su Pratica
            ->get()
            ->map(fn ($s) => [
                'id'        => $s->id,
                'name'      => $s->name,
                'color'     => $s->color,
                'count'     => $s->pratiche_count,
                'is_closed' => $s->is_closed,
            ]);

        $total          = $statuses->sum('count');
        $open           = $statuses->where('is_closed', false)->sum('count');
        $closed         = $statuses->where('is_closed', true)->sum('count');
        $oggi = $this->oggiLavoraSu();

        // Trend mensile ultimi 12 mesi (Pratica è auto-scoped al tenant corrente)
        $trend = [];
        for ($i = 11; $i >= 0; $i--) {
            $date    = Carbon::now()->subMonths($i)->startOfMonth();
            $count   = Pratica::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();
            $trend[] = [
                'month' => $date->format('Y-m'),
                'count' => $count,
            ];
        }

        return Inertia::render('Dashboard', [
            'stats' => [
                'total'          => $total,
                'open'           => $open,
                'closed'         => $closed,
                'todayReminders' => $oggi->count(),
                'statuses'       => $statuses->values(),
            ],
            'trend' => $trend,
            'oggi'  => $oggi->take(50)->values(),
        ]);
    }

    /**
     * "Oggi lavora su": i sinistri che hanno ricevuto l'avviso oggi (registro email) più quelli
     * con l'avviso in scadenza oggi o arretrato non ancora partito (stesso filtro di ProcessDailyReminders:
     * sui sinistri chiusi l'avviso non parte). I più vecchi in cima.
     */
    private function oggiLavoraSu()
    {
        // "Oggi" all'ora italiana (il server è in UTC): l'avviso delle 7 resta in lista fino a mezzanotte.
        $inizioGiornata = now('Europe/Rome')->startOfDay()->utc();

        $avvisi = EmailLog::where('tipo', 'avviso')
            ->where('created_at', '>=', $inizioGiornata)
            ->whereNotNull('pratica_id')
            ->latest()
            ->get(['pratica_id', 'status'])
            ->unique('pratica_id')
            ->keyBy('pratica_id');

        // Nel demo le email non partono di proposito: lì l'avviso "saltato" vale come inviato.
        $demo = (bool) Tenant::find(TenantContext::id())?->getSetting('demo');

        return Pratica::with(['cliente:id,nome', 'veicolo:id,targa', 'currentStatus:id,name,color'])
            ->where(fn ($q) => $q
                ->whereIn('id', $avvisi->keys())
                ->orWhere(fn ($q) => $q
                    ->whereDate('data_prossimo_avviso', '<=', today())
                    ->where(fn ($q) => $q->whereNull('current_status_id')
                        ->orWhereHas('currentStatus', fn ($s) => $s->where('is_closed', false)))))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Pratica $p) => [
                'id'        => $p->id,
                'cliente'   => $p->cliente?->nome,
                'targa'     => $p->veicolo?->targa,
                'compagnia' => $p->compagnia,
                'stato'     => $p->currentStatus ? ['name' => $p->currentStatus->name, 'color' => $p->currentStatus->color] : null,
                'aperto_il' => $p->created_at?->toDateString(),
                // inviato | non_inviato (errore o nessun destinatario) | in_arrivo (non ancora partito oggi)
                'avviso'    => match ($avvisi[$p->id]->status ?? null) {
                    'sent'  => 'inviato',
                    'skipped' => $demo ? 'inviato' : 'non_inviato',
                    null    => 'in_arrivo',
                    default => 'non_inviato',
                },
            ]);
    }
}
