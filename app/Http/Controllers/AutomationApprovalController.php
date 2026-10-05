<?php

namespace App\Http\Controllers;

use App\Jobs\ExecuteClienteAutomationJob;
use App\Models\AutomationApproval;
use App\Services\AutomationPlanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Promemoria programmati (scadenze date dei clienti) in attesa di conferma: l'utente
 * rivede messaggio e destinatari, li modifica e solo allora il messaggio parte.
 */
class AutomationApprovalController extends Controller
{
    public function index(AutomationPlanner $planner): Response
    {
        $this->authorizeAccess();

        $items = AutomationApproval::with(['automation', 'cliente:id,nome,email,telefono,custom_fields,tenant_id'])
            ->where('status', 'pending')
            ->orderBy('field_value')
            ->limit(200)
            ->get()
            ->filter(fn (AutomationApproval $a) => $a->automation && $a->cliente)
            ->map(fn (AutomationApproval $a) => [
                'id'          => $a->id,
                'cliente'     => $a->cliente->nome,
                'field_value' => $a->field_value,
                'plan'        => $planner->planCliente($a->cliente, $a->automation, $a->field_name),
            ])
            ->values();

        return Inertia::render('AutomazioniDaConfermare/Index', ['items' => $items]);
    }

    public function confirm(Request $request, AutomationApproval $approval): RedirectResponse
    {
        $this->authorizeAccess();
        abort_unless($approval->status === 'pending', 409);

        $override = AutomationPlanner::normalizeOverrides($request->input('automation_overrides'))[$approval->automation_id] ?? null;

        if ($override && ! $override['send']) {
            return $this->discard($approval);
        }

        ExecuteClienteAutomationJob::dispatch(
            $approval->cliente,
            $approval->automation,
            $approval->field_name,
            $override ? ['recipients' => $override['recipients'], 'cc' => $override['cc']] : null
        );

        $approval->update(['status' => 'sent', 'decided_by' => auth()->id(), 'decided_at' => now()]);

        return back()->with('success', 'Promemoria inviato.');
    }

    public function discard(AutomationApproval $approval): RedirectResponse
    {
        $this->authorizeAccess();
        abort_unless($approval->status === 'pending', 409);

        $approval->update(['status' => 'discarded', 'decided_by' => auth()->id(), 'decided_at' => now()]);

        return back()->with('success', 'Promemoria scartato: non verrà inviato.');
    }

    private function authorizeAccess(): void
    {
        abort_if(auth()->user()->role === 'external', 403);
    }
}
