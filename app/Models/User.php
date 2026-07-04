<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'tenant_id', 'role', 'is_active'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function pratiche(): HasMany
    {
        return $this->hasMany(Pratica::class, 'utente_creatore_id');
    }

    public function praticaNote(): HasMany
    {
        return $this->hasMany(PraticaNota::class);
    }

    public function scopeTenantAdmin(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId)->where('role', 'tenant-admin');
    }

    public function isTenantAdmin(): bool
    {
        return $this->role === 'tenant-admin';
    }
}
