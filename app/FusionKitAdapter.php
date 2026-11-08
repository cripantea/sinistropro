<?php

namespace App;

use App\FusionKit\Adapter;
use App\FusionKit\LinkError;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Fusion Kit: SinistroPro visto da Fusion ID e Fusion Control (clienti = agenzie/tenant). */
class FusionKitAdapter implements Adapter
{
    public function linkUser(array $claims): Authenticatable
    {
        $user = User::where('fusion_id_sub', $claims['sub'])->first();
        if (! $user) {
            if (! $claims['email_verified']) {
                throw new LinkError('Conferma prima la tua email (trovi il link nella posta), poi riprova.');
            }
            $user = User::where('email', $claims['email'])->first();
            if (! $user) {
                throw new LinkError("Non c'è un account SinistroPro per {$claims['email']}. Chiedi un invito alla tua agenzia.");
            }
            if ($user->fusion_id_sub && $user->fusion_id_sub !== $claims['sub']) {
                throw new LinkError('Questo account è già collegato a un altro accesso: entra con quello o scrivi all\'assistenza.');
            }
            $user->forceFill(['fusion_id_sub' => $claims['sub']])->save();
        }
        if (! $user->is_active) {
            throw new LinkError('Il tuo account è stato disabilitato. Contatta l\'amministratore.');
        }
        if ($user->role === 'superadmin' && ! in_array('mfa', $claims['amr'], true)) {
            throw new LinkError('Per entrare come superadmin serve la verifica in due passaggi: attivala nella pagina di accesso.');
        }
        if ($user->role !== 'superadmin' && $user->tenant?->suspended_at) {
            throw new LinkError('L\'account della tua agenzia è sospeso: contatta Fusionsoft.');
        }

        return $user;
    }

    public function homeFor(Authenticatable $user): string
    {
        return $user->role === 'superadmin' ? route('superadmin.dashboard', absolute: false) : route('dashboard', absolute: false);
    }

    public function widgetUser(Authenticatable $user): ?array
    {
        return [
            'id' => (string) $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'role' => $user->role,
            'tenantId' => $user->tenant_id ? (string) $user->tenant_id : null,
            'tenantName' => $user->tenant?->name,
        ];
    }

    public function checks(): array
    {
        return [
            'database' => fn () => DB::select('select 1 as ok')[0]->ok == 1,
        ];
    }

    public function tenants(): array
    {
        return Tenant::query()->withCount('users')->orderBy('id')->get()->map(function (Tenant $t) {
            $status = $t->suspended_at ? 'suspended'
                : ($t->isBilledExternally() || $t->hasAccess() ? 'active' : 'cancelled');

            return [
                'id' => (string) $t->id,
                'name' => $t->name,
                'status' => $t->subscription_status === 'trialing' && ! $t->suspended_at ? 'trial' : $status,
                'plan' => $t->isBilledExternally() ? 'Fatturato a parte' : ($t->subscription_status ? 'Stripe · '.$t->subscription_status : 'Stripe'),
                'users' => (int) $t->users_count,
                'createdAt' => optional($t->created_at)->toIso8601String() ?? now()->toIso8601String(),
                'lastActivityAt' => optional($t->updated_at)->toIso8601String(),
                'contactEmail' => User::tenantAdmin($t->id)->value('email'),
            ];
        })->all();
    }

    public function users(): array
    {
        return User::query()->with('tenant:id,name')->orderBy('id')->get()->map(fn (User $u) => [
            'id' => (string) $u->id,
            'email' => $u->email,
            'name' => $u->name,
            'role' => $u->is_active ? $u->role : 'disattivato',
            'tenantId' => $u->tenant_id ? (string) $u->tenant_id : null,
            'tenantName' => $u->tenant?->name,
            'fusionIdSub' => $u->fusion_id_sub,
            'createdAt' => optional($u->created_at)->toIso8601String() ?? now()->toIso8601String(),
        ])->all();
    }

    public function setTenantSuspended(string $tenantId, bool $suspended): void
    {
        $tenant = Tenant::findOrFail($tenantId);
        $tenant->forceFill(['suspended_at' => $suspended ? now() : null])->save();
        if ($suspended) {
            $ids = User::where('tenant_id', $tenant->id)->pluck('id');
            DB::table('sessions')->whereIn('user_id', $ids)->delete();
        }
    }

    public function impersonationTarget(string $tenantId, ?string $userId): ?string
    {
        $query = User::where('tenant_id', $tenantId)->where('is_active', true);
        $user = $userId ? $query->whereKey($userId)->first() : (clone $query)->where('role', 'tenant-admin')->orderBy('id')->first() ?? $query->orderBy('id')->first();

        return $user ? (string) $user->id : null;
    }

    public function revokeSessions(string $email): int
    {
        $user = User::where('email', $email)->first();
        if (! $user) {
            return 0;
        }
        // Anche il cookie "ricordami" smette di valere.
        $user->forceFill(['remember_token' => Str::random(60)])->save();

        return DB::table('sessions')->where('user_id', $user->id)->delete();
    }
}
