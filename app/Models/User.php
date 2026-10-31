<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\InternalRole;
use App\Notifications\ResetPasswordNotification;
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
        'jabatan',
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
            if (! $this->hasRole('transportir')) {
                return route('registration.pending.internal');
            }

            // Transportir yang sudah mengirim profil masuk antrean verifikasi.
            $hasSubmittedProfile = $this->transporters()
                ->whereNotNull('profile_submitted_at')
                ->exists();

            return $hasSubmittedProfile
                ? route('registration.pending.transporter')
                : route('registration.profile.edit');
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

    /**
     * Hanya akun yang mendaftar lewat email + kata sandi yang dapat mengatur ulang
     * kata sandi. Akun yang mendaftar lewat Google tidak memiliki kata sandi.
     */
    public function canResetPassword(): bool
    {
        return $this->google_id === null && filled($this->password);
    }

    /**
     * Kirim tautan reset kata sandi memakai notifikasi O-KIM (bahasa Indonesia).
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * User internal (TAD: HSSE/QQ/Distribusi) — hanya boleh mengisi checklist.
     */
    public function isTad(): bool
    {
        return $this->hasRole(InternalRole::TAD_ROLES);
    }

    /**
     * Verifikasi tindak lanjut temuan.
     *
     * Aturan:
     * 1. Super admin selalu boleh — jalan terakhir agar temuan tidak macet
     *    permanen (mis. temuan tercatat dibuat oleh akun non-TAD).
     * 2. Setiap TAD boleh memverifikasi temuan pada fungsi yang sama, tidak
     *    terbatas pada temuan yang ia buat sendiri, sehingga pemeriksaan tidak
     *    tertahan saat petugas pembuat tidak tersedia.
     * 3. Pengawas boleh memverifikasi temuan yang dibuat oleh user TAD.
     */
    public function canVerifyFinding(Finding $finding): bool
    {
        if ($this->hasRole(InternalRole::SuperAdmin->value)) {
            return true;
        }

        $dept = $finding->department->value;

        if ($this->isTad()) {
            return $this->can('inspect_'.$dept);
        }

        if (! $this->can('approve_tad_findings')) {
            return false;
        }

        return $finding->creator?->isTad() ?? false;
    }
}
