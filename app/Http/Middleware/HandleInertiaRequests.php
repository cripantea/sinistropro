<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user'            => $request->user(),
                'isImpersonating' => $request->session()->has('impersonator_id'),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
            ],
            'notifications' => $this->recentNotifications($request),
        ];
    }

    protected function recentNotifications(Request $request): array
    {
        $user = $request->user();
        if (! $user || ! $user->tenant_id) {
            return [];
        }

        return \App\Models\AuditLog::with('user:id,name')
            ->where('tenant_id', $user->tenant_id)
            ->latest()
            ->limit(5)
            ->get(['id', 'user_id', 'action', 'auditable_type', 'created_at'])
            ->map(fn ($log) => [
                'id'         => $log->id,
                'user_name'  => $log->user?->name ?? 'Sistema',
                'action'     => $log->action,
                'model'      => class_basename($log->auditable_type),
                'created_at' => $log->created_at->toIso8601String(),
            ])
            ->all();
    }
}
