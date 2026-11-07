<?php

namespace App\FusionKit;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Cosa ogni prodotto Laravel deve implementare per il Fusion Kit (App\FusionKitAdapter,
 * registrato nell'AppServiceProvider). Il resto (OIDC, API, widget) è comune.
 */
interface Adapter
{
    /** Collega l'identità di Fusion ID a un utente del prodotto. Lancia LinkError con un messaggio per l'utente. */
    public function linkUser(array $claims): Authenticatable;

    /** Pagina dopo il login. */
    public function homeFor(Authenticatable $user): string;

    /** Utente del widget Assistenza: [id, email, name, role, tenantId, tenantName], o null. */
    public function widgetUser(Authenticatable $user): ?array;

    /** @return array<string, callable(): bool> controlli di salute */
    public function checks(): array;

    /** @return list<array{id:string,name:string,status:string,plan:?string,users:int,createdAt:string,lastActivityAt:?string,contactEmail:?string}> */
    public function tenants(): array;

    /** @return list<array{id:string,email:string,name:?string,role:string,tenantId:?string,tenantName:?string,fusionIdSub:?string,createdAt:string}> */
    public function users(): array;

    public function setTenantSuspended(string $tenantId, bool $suspended): void;

    /** Id dell'utente con cui entrare per "entra come" (l'admin del cliente se $userId è null). */
    public function impersonationTarget(string $tenantId, ?string $userId): ?string;

    /** Chiude tutte le sessioni dell'email. Restituisce quante. */
    public function revokeSessions(string $email): int;
}
