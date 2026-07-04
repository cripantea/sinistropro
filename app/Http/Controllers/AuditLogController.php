<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $user = auth()->user();

        abort_unless(
            $user->isTenantAdmin() || $user->role === 'superadmin',
            403,
            'Accesso riservato agli amministratori.'
        );

        $query = AuditLog::with(['user:id,name,email', 'impersonator:id,name,email'])
            ->where('tenant_id', $user->tenant_id)
            ->latest();

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('model')) {
            $query->where('auditable_type', 'like', '%' . $request->input('model') . '%');
        }

        $logs = $query->paginate(50)->withQueryString();

        return Inertia::render('AuditLogs/Index', [
            'logs'    => $logs,
            'filters' => $request->only(['action', 'user_id', 'model']),
        ]);
    }
}
