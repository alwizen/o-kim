<?php

namespace App\Models;

use App\Enums\TransporterStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transporter extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_name',
        'company_address',
        'phone',
        'legal_identifier',
        'status',
        'rejection_reason',
        'profile_submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TransporterStatus::class,
            'profile_submitted_at' => 'datetime',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function kimApplications(): HasMany
    {
        return $this->hasMany(KimApplication::class);
    }
}
