<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Inspection = 'inspection';
    case RevisionRequired = 'revision_required';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Issued = 'issued';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Submitted => 'Terkirim',
            self::Inspection => 'Dalam Pemeriksaan',
            self::RevisionRequired => 'Perlu Perbaikan',
            self::PendingApproval => 'Menunggu Persetujuan',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::Issued => 'KIM Terbit',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** @return array<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted, self::Cancelled],
            self::Submitted => [self::Inspection, self::Cancelled],
            self::Inspection => [self::RevisionRequired, self::PendingApproval, self::Rejected],
            self::RevisionRequired => [self::Inspection, self::Cancelled],
            self::PendingApproval => [self::Approved, self::Rejected],
            self::Approved => [self::Issued],
            self::Rejected, self::Issued, self::Cancelled => [],
        };
    }

    public function isOpen(): bool
    {
        return match ($this) {
            self::Draft, self::Submitted, self::Inspection,
            self::RevisionRequired, self::PendingApproval => true,
            default => false,
        };
    }
}
