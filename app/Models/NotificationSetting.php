<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
    protected $fillable = [
        'event_key',
        'mail_enabled',
        'roles',
    ];

    protected function casts(): array
    {
        return [
            'mail_enabled' => 'boolean',
            'roles' => 'array',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function roleNames(): array
    {
        return array_values((array) ($this->roles ?? []));
    }
}
