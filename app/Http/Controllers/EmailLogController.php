<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Registro delle email di sistema (avvisi, automazioni, notifiche di stato):
 * mostra cosa è stato inviato, cosa è fallito e cosa è stato saltato e perché.
 */
class EmailLogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(auth()->user()->role !== 'external', 403);

        $status = in_array($request->input('status'), ['sent', 'failed', 'skipped'], true) ? $request->input('status') : null;

        $logs = EmailLog::with('pratica:id')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('EmailLog/Index', [
            'logs'    => $logs,
            'filters' => ['status' => $status],
        ]);
    }
}
