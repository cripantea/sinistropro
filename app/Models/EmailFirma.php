<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Firma in fondo alle email: personale (user_id) o di tutta l'agenzia (user_id vuoto).
 * Può avere un'immagine (es. la firma grafica col logo), che nell'email va incorporata.
 */
class EmailFirma extends Model
{
    use BelongsToTenant;

    protected $table = 'email_firme';

    protected $fillable = ['tenant_id', 'user_id', 'nome', 'testo', 'immagine', 'immagine_mime'];

    /** Firme che l'utente può usare: le sue e quelle dell'agenzia. */
    public function scopeDisponibiliPer(Builder $query, User $user): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $user->id));
    }

    /** Le firme dell'agenzia le cambia l'amministratore; le personali solo il proprietario. */
    public function modificabileDa(User $user): bool
    {
        return $this->user_id === null ? self::gestisceAgenzia($user) : $this->user_id === $user->id;
    }

    public static function gestisceAgenzia(User $user): bool
    {
        return $user->isTenantAdmin() || $user->role === 'superadmin';
    }

    /** Firma di agenzia proposta a tutti e usata dalle automazioni (Impostazioni firme → "Predefinita per tutti"). */
    public static function predefinitaPer(?Tenant $tenant): ?self
    {
        $id = $tenant?->getSetting('firma_email_predefinita');

        return $id ? self::acrossAllTenants()->where('tenant_id', $tenant->id)->whereNull('user_id')->find($id) : null;
    }

    /** Identificativo dell'immagine incorporata nell'email (src="cid:…"). */
    public function cid(): string
    {
        return "firma-{$this->id}@sinistropro";
    }

    public function urlImmagine(): ?string
    {
        return $this->immagine ? route('email.firme.immagine', $this, false).'?v='.$this->updated_at?->timestamp : null;
    }

    public function contenutoImmagine(): ?string
    {
        return $this->immagine ? Storage::disk('s3')->get($this->immagine) : null;
    }

    /**
     * HTML da mettere in fondo al messaggio. Nell'email vera l'immagine è incorporata (cid),
     * nella copia salvata in SinistroPro punta all'indirizzo interno per vederla.
     */
    public function html(bool $perEmail = true): string
    {
        $html = '<br><br>';
        if (trim((string) $this->testo) !== '') {
            $html .= nl2br(e(trim($this->testo)));
        }
        if ($this->immagine) {
            $src = $perEmail ? 'cid:'.$this->cid() : $this->urlImmagine();
            $html .= (trim((string) $this->testo) !== '' ? '<br>' : '').'<img src="'.e($src).'" alt="'.e($this->nome).'" style="max-width:600px;height:auto;border:0">';
        }

        return $html;
    }
}
