<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenantContext;
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
            'notifications'  => $this->recentNotifications($request),
            'tenantFeatures' => $this->tenantFeatures($request),
            'impersonating'  => $this->impersonatingInfo($request),
            'tenantContext'  => $this->tenantContextInfo($request),
        ];
    }

    protected function tenantFeatures(Request $request): array
    {
        $tenant = $this->currentTenant($request);
        if (! $tenant) return [];

        return collect(array_keys(Tenant::AVAILABLE_FEATURES))
            ->mapWithKeys(fn ($key) => [$key => $tenant->hasFeature($key)])
            ->all();
    }

    protected function impersonatingInfo(Request $request): ?array
    {
        if (! $request->session()->has('impersonator_id')) return null;
        $tenant = $this->currentTenant($request);
        return $tenant ? ['tenant_id' => $tenant->id, 'tenant_name' => $tenant->name] : null;
    }

    protected function tenantContextInfo(Request $request): ?array
    {
        $user = $request->user();
        if ($user?->role !== 'superadmin') return null;

        $tenantId = session('sa_tenant_id');
        if (! $tenantId) return null;

        $tenant = $this->currentTenant($request);
        return $tenant ? ['tenant_id' => $tenant->id, 'tenant_name' => $tenant->name] : null;
    }

    private ?Tenant $cachedTenant = null;

    protected function currentTenant(Request $request): ?Tenant
    {
        if ($this->cachedTenant) return $this->cachedTenant;
        $tenantId = TenantContext::id();
        if (! $tenantId) return null;
        return $this->cachedTenant = Tenant::find($tenantId);
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
