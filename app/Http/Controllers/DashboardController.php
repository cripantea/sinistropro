<?php

namespace App\Http\Controllers;

use App\Models\Pratica;
use App\Models\TenantStatus;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $user     = auth()->user();
        $tenantId = $user->tenant_id;

        // Superadmin senza tenant → dashboard vuota con banner di ritorno
        if (! $tenantId) {
            return Inertia::render('Dashboard', [
                'stats' => ['total' => 0, 'open' => 0, 'closed' => 0, 'todayReminders' => 0, 'statuses' => []],
                'trend' => [],
            ]);
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
        $todayReminders = Pratica::whereDate('data_prossimo_avviso', today())->count();

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
                'todayReminders' => $todayReminders,
                'statuses'       => $statuses->values(),
            ],
            'trend' => $trend,
        ]);
    }
}
