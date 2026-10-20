<?php

namespace App\Models;

use App\Enums\AccountStatus;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'phone',
        'account_status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'account_status' => AccountStatus::class,
            'password' => 'hashed',
        ];
    }

    public function reviewer()
    {
        return $this->belongsTo(self::class, 'reviewed_by');
    }

    public function transporters(): BelongsToMany
    {
        return $this->belongsToMany(Transporter::class)->withTimestamps();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->account_status !== AccountStatus::Approved) {
            return false;
        }

        return match ($panel->getId()) {
            'admin' => $this->can('access_admin_panel'),
            'transportir' => $this->hasRole('transportir'),
            default => false,
        };
    }

    /**
     * Tujuan halaman yang tepat untuk pengguna yang sudah login.
     */
    public function homeUrl(): string
    {
        if ($this->account_status === AccountStatus::Approved) {
            if ($this->can('access_admin_panel')) {
                return '/admin';
            }

            return '/transportir';
        }

        if ($this->account_status === AccountStatus::Pending) {
            return $this->hasRole('transportir')
                ? route('registration.profile.edit')
                : route('registration.pending.internal');
        }

        return '/';
    }

    /**
     * Akun dapat masuk ke area operasional (bukan pending/rejected/suspended).
     */
    public function hasOperationalAccess(): bool
    {
        return $this->account_status === AccountStatus::Approved;
    }
}
