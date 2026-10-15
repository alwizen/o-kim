<?php

namespace App\Enums;

enum ApprovalDecision: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
        };
    }
}
