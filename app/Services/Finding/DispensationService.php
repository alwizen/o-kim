<?php

namespace App\Services\Finding;

use App\Enums\ApplicationStatus;
use App\Enums\DispensationAction;
use App\Enums\DispensationRequestStatus;
use App\Enums\FindingStatus;
use App\Enums\InternalRole;
use App\Enums\KimCardStatus;
use App\Models\Finding;
use App\Models\FindingDispensation;
use App\Models\FindingDispensationRequest;
use App\Models\KimApplication;
use App\Models\User;
use App\Notifications\DispensationRequestApproved;
use App\Notifications\DispensationRequestRejected;
use App\Notifications\DispensationRequestSubmitted;
use App\Notifications\FindingDispensationGranted;
use App\Notifications\FindingDispensationGrantedStaff;
use App\Services\Kim\KimValidityService;
use App\Support\AppNotifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DispensationService
{
    private const MIN_REASON_LENGTH = 10;

    public function __construct(private KimValidityService $validityService) {}

    /**
     * Dispensasi hanya boleh diberikan oleh Fuel Terminal Manager.
     * Permission saja tidak cukup: role diperiksa eksplisit (defense in depth).
     */
    public function ensureFtm(User $user): void
    {
        if (! $user->can('dispensate_findings') || ! $user->hasRole(InternalRole::Ftm->value)) {
            throw ValidationException::withMessages([
                'authority' => 'Dispensasi temuan hanya dapat diberikan oleh Fuel Terminal Manager (FTM).',
            ]);
        }
    }

    /**
     * Transportir mengajukan permohonan dispensasi atas sebuah temuan.
     *
     * Permohonan berstatus pending sampai FTM memutuskan. Temuan tetap berstatus
     * terbuka (tetap menghalangi approval) selama menunggu keputusan.
     */
    public function request(Finding $finding, User $requester, string $reason): FindingDispensationRequest
    {
        $this->ensureRequesterOwnsFinding($finding, $requester);

        $application = $finding->application;

        if (! in_array($application->status, [ApplicationStatus::Inspection, ApplicationStatus::RevisionRequired], true)) {
            throw ValidationException::withMessages([
                'status' => 'Permohonan dispensasi hanya dapat diajukan selama tahap pemeriksaan.',
            ]);
        }

        if (! in_array($finding->status, [FindingStatus::Open, FindingStatus::Rejected], true)) {
            throw ValidationException::withMessages([
                'finding' => 'Dispensasi hanya dapat diajukan untuk temuan berstatus terbuka atau ditolak.',
            ]);
        }

        $hasPending = $finding->dispensationRequests()
            ->where('status', DispensationRequestStatus::Pending->value)
            ->exists();

        if ($hasPending) {
            throw ValidationException::withMessages([
                'finding' => 'Temuan ini sudah memiliki permohonan dispensasi yang menunggu keputusan FTM.',
            ]);
        }

        $this->ensureReason($reason);

        $request = DB::transaction(function () use ($finding, $requester, $reason) {
            return FindingDispensationRequest::create([
                'finding_id' => $finding->id,
                'requested_by' => $requester->id,
                'reason' => $reason,
                'status' => DispensationRequestStatus::Pending,
            ]);
        });

        $application = $finding->application;
        $registrationNumber = $application?->vehicle?->registration_number ?? '-';

        DB::afterCommit(fn () => AppNotifier::notifyRole(
            InternalRole::Ftm->value,
            new DispensationRequestSubmitted(
                $application?->application_number ?? '-',
                $registrationNumber,
                $finding->department->label(),
                (string) $finding->description,
                $requester->name,
                (string) $reason,
            ),
        ));

        return $request;
    }

    /**
     * FTM menyetujui permohonan dispensasi dan menetapkan batas perbaikan.
     */
    public function approveRequest(
        FindingDispensationRequest $request,
        User $ftm,
        Carbon|string $dueDate,
        ?string $notes = null,
    ): FindingDispensationRequest {
        $this->ensureFtm($ftm);
        $this->ensureRequestPending($request);

        // grant() menetapkan status temuan menjadi dispensasi, mencatat riwayat,
        // serta mengirim notifikasi pemberian dispensasi ke transportir & staf.
        $granted = $this->grant($request->finding, $ftm, $dueDate, $request->reason);

        return DB::transaction(function () use ($request, $ftm, $granted, $notes) {
            $request->update([
                'status' => DispensationRequestStatus::Approved,
                'due_date' => $granted->dispensation_due_date?->toDateString(),
                'decided_by' => $ftm->id,
                'decided_at' => now(),
                'decision_notes' => $notes,
            ]);

            $application = $request->finding?->application;
            $dueDate = $request->due_date?->translatedFormat('d M Y') ?? '-';

            DB::afterCommit(fn () => $application?->transporter_id
                ? AppNotifier::notifyTransporter(
                    $application->transporter_id,
                    new DispensationRequestApproved(
                        $application->application_number,
                        $application->vehicle?->registration_number ?? '-',
                        $dueDate,
                    ),
                )
                : null);

            return $request->fresh();
        });
    }

    /**
     * FTM menolak permohonan dispensasi. Temuan tetap terbuka.
     */
    public function rejectRequest(
        FindingDispensationRequest $request,
        User $ftm,
        string $reason,
    ): FindingDispensationRequest {
        $this->ensureFtm($ftm);
        $this->ensureRequestPending($request);

        if (mb_strlen(trim($reason)) < self::MIN_REASON_LENGTH) {
            throw ValidationException::withMessages([
                'decision_notes' => 'Alasan penolakan wajib diisi minimal '.self::MIN_REASON_LENGTH.' karakter.',
            ]);
        }

        return DB::transaction(function () use ($request, $ftm, $reason) {
            $request->update([
                'status' => DispensationRequestStatus::Rejected,
                'decided_by' => $ftm->id,
                'decided_at' => now(),
                'decision_notes' => $reason,
            ]);

            $application = $request->finding?->application;

            DB::afterCommit(fn () => $application?->transporter_id
                ? AppNotifier::notifyTransporter(
                    $application->transporter_id,
                    new DispensationRequestRejected(
                        $application->application_number,
                        $application->vehicle?->registration_number ?? '-',
                        $reason,
                    ),
                )
                : null);

            return $request->fresh();
        });
    }

    private function ensureRequestPending(FindingDispensationRequest $request): void
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'request' => 'Permohonan dispensasi ini sudah diputuskan.',
            ]);
        }
    }

    private function ensureRequesterOwnsFinding(Finding $finding, User $user): void
    {
        $owns = $user->transporters()
            ->where('transporters.id', $finding->application?->transporter_id)
            ->exists();

        if (! $owns) {
            throw ValidationException::withMessages([
                'finding' => 'Anda tidak memiliki akses ke temuan ini.',
            ]);
        }
    }

    public function grant(Finding $finding, User $ftm, Carbon|string $dueDate, string $reason): Finding
    {
        $this->ensureFtm($ftm);

        $dueDate = $this->parseDueDate($dueDate);

        $application = $finding->application;

        if (! in_array($application->status, [ApplicationStatus::Inspection, ApplicationStatus::RevisionRequired], true)) {
            throw ValidationException::withMessages([
                'status' => 'Dispensasi hanya dapat diberikan selama tahap pemeriksaan.',
            ]);
        }

        if (! in_array($finding->status, [FindingStatus::Open, FindingStatus::Rejected], true)) {
            throw ValidationException::withMessages([
                'finding' => 'Dispensasi hanya dapat diberikan untuk temuan berstatus terbuka atau ditolak.',
            ]);
        }

        // Aturan §3.1: temuan yang dispensasinya sudah pernah kedaluwarsa harus
        // melewati checklist ulang, bukan didispensasi ulang.
        $lastDispensation = $finding->dispensations()->latest('id')->first();

        if ($lastDispensation?->due_date !== null
            && $lastDispensation->due_date->lt(Carbon::today())) {
            throw ValidationException::withMessages([
                'finding' => 'Temuan ini sudah pernah didispensasi dan kedaluwarsa. Lakukan checklist ulang terlebih dahulu.',
            ]);
        }

        $this->ensureDueDateInFuture($dueDate);
        $this->ensureReason($reason);

        return DB::transaction(function () use ($finding, $ftm, $dueDate, $reason) {
            $finding = Finding::lockForUpdate()->findOrFail($finding->id);

            $finding->update([
                'status' => FindingStatus::Dispensation,
                'dispensation_due_date' => $dueDate->toDateString(),
                'dispensation_reason' => $reason,
                'dispensated_by' => $ftm->id,
                'dispensated_at' => now(),
            ]);

            $this->record($finding, $ftm, DispensationAction::Granted, $dueDate, $reason);

            $this->notifyGranted($finding->fresh(), $ftm);

            return $finding;
        });
    }

    public function extend(Finding $finding, User $ftm, Carbon|string $newDueDate, ?string $notes = null): Finding
    {
        $this->ensureFtm($ftm);

        if ($finding->status !== FindingStatus::Dispensation) {
            throw ValidationException::withMessages([
                'finding' => 'Hanya temuan berstatus dispensasi yang dapat diperpanjang.',
            ]);
        }

        if ($finding->isDispensationExpired()) {
            throw ValidationException::withMessages([
                'finding' => 'Dispensasi sudah kedaluwarsa dan tidak dapat diperpanjang.',
            ]);
        }

        $newDueDate = $this->parseDueDate($newDueDate);
        $this->ensureDueDateInFuture($newDueDate);

        return DB::transaction(function () use ($finding, $ftm, $newDueDate, $notes) {
            $finding = Finding::lockForUpdate()->findOrFail($finding->id);

            $finding->update(['dispensation_due_date' => $newDueDate->toDateString()]);

            $this->record($finding, $ftm, DispensationAction::Extended, $newDueDate, $finding->dispensation_reason, $notes);

            $this->syncKimCard($finding->application);

            return $finding;
        });
    }

    public function updateReason(Finding $finding, User $ftm, string $reason, ?string $notes = null): Finding
    {
        $this->ensureFtm($ftm);

        if ($finding->status !== FindingStatus::Dispensation) {
            throw ValidationException::withMessages([
                'finding' => 'Hanya temuan berstatus dispensasi yang alasannya dapat diubah.',
            ]);
        }

        $this->ensureReason($reason);

        return DB::transaction(function () use ($finding, $ftm, $reason, $notes) {
            $finding = Finding::lockForUpdate()->findOrFail($finding->id);

            $finding->update(['dispensation_reason' => $reason]);

            $this->record($finding, $ftm, DispensationAction::ReasonUpdated, $finding->dispensation_due_date, $reason, $notes);

            return $finding;
        });
    }

    public function revoke(Finding $finding, User $ftm, ?string $notes = null): Finding
    {
        $this->ensureFtm($ftm);

        if ($finding->status !== FindingStatus::Dispensation) {
            throw ValidationException::withMessages([
                'finding' => 'Hanya temuan berstatus dispensasi yang dapat dicabut.',
            ]);
        }

        return DB::transaction(function () use ($finding, $ftm, $notes) {
            $finding = Finding::lockForUpdate()->findOrFail($finding->id);

            $this->record($finding, $ftm, DispensationAction::Revoked, $finding->dispensation_due_date, $finding->dispensation_reason, $notes);

            $finding->update([
                'status' => FindingStatus::Open,
                'dispensation_due_date' => null,
                'dispensation_reason' => null,
                'dispensated_by' => null,
                'dispensated_at' => null,
            ]);

            $this->revokeKimCard($finding->application);

            return $finding;
        });
    }

    /**
     * Sinkronkan batas dispensasi pada kartu KIM yang sudah terbit.
     * Dipakai setelah perpanjangan/pencabutan.
     */
    public function syncKimCard(KimApplication $application): void
    {
        $card = $application->kimCard;

        if ($card === null || ! $card->is_dispensation) {
            return;
        }

        $dueDate = $application->findings()
            ->where('status', FindingStatus::Dispensation->value)
            ->whereNotNull('dispensation_due_date')
            ->min('dispensation_due_date');

        if ($dueDate === null) {
            return;
        }

        $dueDate = Carbon::parse($dueDate);

        $expiresAt = $this->validityService->calculateExpiresAt(
            $application,
            Carbon::parse($card->issued_at),
            $dueDate,
        );

        $card->update([
            'dispensation_expires_at' => $dueDate->toDateString(),
            'expires_at' => $expiresAt->toDateString(),
        ]);

        $application->update(['expires_at' => $expiresAt->toDateString()]);
    }

    private function revokeKimCard(KimApplication $application): void
    {
        $card = $application->kimCard;

        if ($card === null || ! $card->is_dispensation) {
            return;
        }

        $card->update(['status' => KimCardStatus::Revoked]);
    }

    private function record(
        Finding $finding,
        User $user,
        DispensationAction $action,
        Carbon|string|null $dueDate,
        ?string $reason = null,
        ?string $notes = null,
    ): FindingDispensation {
        return $finding->dispensations()->create([
            'user_id' => $user->id,
            'action' => $action,
            'due_date' => $dueDate !== null ? Carbon::parse($dueDate)->toDateString() : null,
            'reason' => $reason,
            'notes' => $notes,
        ]);
    }

    private function notifyGranted(Finding $finding, User $ftm): void
    {
        $application = $finding->application;
        $dueDate = $finding->dispensation_due_date?->translatedFormat('d M Y') ?? '-';
        $registrationNumber = $application->vehicle?->registration_number ?? '-';

        DB::afterCommit(function () use ($application, $finding, $ftm, $dueDate, $registrationNumber) {
            AppNotifier::notifyTransporter(
                $application->transporter_id,
                new FindingDispensationGranted(
                    $application->application_number,
                    $registrationNumber,
                    $finding->department->label(),
                    $dueDate,
                    (string) $finding->dispensation_reason,
                ),
            );

            AppNotifier::notifyRole(
                ['ftm', 'pengawas_hsse', 'pengawas_distribusi', 'pengawas_qq'],
                new FindingDispensationGrantedStaff(
                    $application->application_number,
                    $registrationNumber,
                    $finding->department->label(),
                    $dueDate,
                    (string) $finding->dispensation_reason,
                    $ftm->name,
                ),
            );
        });
    }

    private function parseDueDate(Carbon|string $dueDate): Carbon
    {
        return $dueDate instanceof Carbon
            ? $dueDate->copy()->startOfDay()
            : Carbon::parse($dueDate)->startOfDay();
    }

    private function ensureDueDateInFuture(Carbon $dueDate): void
    {
        if (! $dueDate->gt(Carbon::today())) {
            throw ValidationException::withMessages([
                'dispensation_due_date' => 'Batas dispensasi harus lebih besar dari hari ini.',
            ]);
        }
    }

    private function ensureReason(string $reason): void
    {
        if (mb_strlen(trim($reason)) < self::MIN_REASON_LENGTH) {
            throw ValidationException::withMessages([
                'dispensation_reason' => 'Alasan dispensasi wajib diisi minimal '.self::MIN_REASON_LENGTH.' karakter.',
            ]);
        }
    }
}
