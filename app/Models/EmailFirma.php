<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Firma in fondo alle email: personale (user_id) o di tutta l'agenzia (user_id vuoto). */
class EmailFirma extends Model
{
    use BelongsToTenant;

    protected $table = 'email_firme';

    protected $fillable = ['tenant_id', 'user_id', 'nome', 'testo'];

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

    /** Testo semplice → HTML da mettere in fondo al messaggio. */
    public function html(): string
    {
        return '<br><br>'.nl2br(e(trim($this->testo)));
    }
}
