<?php

namespace App\Services\Finding;

use App\Enums\ApplicationStatus;
use App\Enums\FindingStatus;
use App\Models\Finding;
use App\Models\FindingAction;
use App\Models\FindingActionAttachment;
use App\Models\FindingVerification;
use App\Models\User;
use App\Notifications\FindingActionSubmitted;
use App\Support\AppNotifier;
use App\Support\SafeFilename;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class FindingService
{
    private const DISK = 'local';

    /**
     * Transportir mengirim tindak lanjut atas temuan.
     *
     * @param  array<UploadedFile>  $files
     */
    public function submitAction(Finding $finding, User $submitter, string $description, array $files = []): FindingAction
    {
        $this->ensureSubmitterOwnsFinding($finding, $submitter);

        if (! in_array($finding->status, [FindingStatus::Open, FindingStatus::Rejected], true)) {
            throw ValidationException::withMessages([
                'finding' => 'Temuan tidak dalam status yang dapat ditindaklanjuti.',
            ]);
        }

        return DB::transaction(function () use ($finding, $submitter, $description, $files) {
            $action = FindingAction::create([
                'finding_id' => $finding->id,
                'submitted_by' => $submitter->id,
                'description' => $description,
            ]);

            foreach ($files as $file) {
                $path = $file->store("finding-actions/{$action->id}", ['disk' => self::DISK]);

                FindingActionAttachment::create([
                    'finding_action_id' => $action->id,
                    'file_path' => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }

            $finding->update(['status' => FindingStatus::SubmittedForVerification]);

            return $action;
        });

        $this->notifyVerifierOfAction($finding);
    }

    private function notifyVerifierOfAction(Finding $finding): void
    {
        AppNotifier::notifyStaffWithPermission(
            'inspect_'.$finding->department->value,
            new FindingActionSubmitted(
                $finding->application?->application_number ?? '-',
                $finding->department->label(),
            ),
        );
    }

    /**
     * Petugas fungsi memverifikasi tindak lanjut temuan.
     */
    public function verifyAction(Finding $finding, FindingAction $action, User $verifier, bool $accepted, ?string $notes = null): FindingVerification
    {
        if (! $verifier->can('inspect_'.$finding->department->value)) {
            throw ValidationException::withMessages([
                'verifier' => 'Anda tidak berwenang memverifikasi temuan fungsi '.$finding->department->label().'.',
            ]);
        }

        if ($finding->status !== FindingStatus::SubmittedForVerification) {
            throw ValidationException::withMessages([
                'finding' => 'Temuan tidak sedang menunggu verifikasi.',
            ]);
        }

        if (! $accepted && blank($notes)) {
            throw ValidationException::withMessages([
                'notes' => 'Penolakan tindak lanjut wajib disertai catatan.',
            ]);
        }

        return DB::transaction(function () use ($finding, $action, $verifier, $accepted, $notes) {
            $verification = FindingVerification::create([
                'finding_id' => $finding->id,
                'finding_action_id' => $action->id,
                'verified_by' => $verifier->id,
                'decision' => $accepted ? 'accepted' : 'rejected',
                'notes' => $notes,
                'verified_at' => now(),
            ]);

            if ($accepted) {
                $finding->update([
                    'status' => FindingStatus::Verified,
                    'closed_at' => now(),
                ]);

                $this->maybeReturnToInspection($finding);
            } else {
                $finding->update(['status' => FindingStatus::Rejected]);
            }

            return $verification;
        });
    }

    public function attachmentDownload(FindingActionAttachment $attachment)
    {
        abort_unless(Storage::disk(self::DISK)->exists($attachment->file_path), 404);

        return Storage::disk(self::DISK)->download(
            $attachment->file_path,
            SafeFilename::make($attachment->original_filename)
        );
    }

    private function ensureSubmitterOwnsFinding(Finding $finding, User $user): void
    {
        $owns = $user->transporters()
            ->where('transporters.id', $finding->application->transporter_id)
            ->exists();

        if (! $owns) {
            throw ValidationException::withMessages([
                'finding' => 'Anda tidak memiliki akses ke temuan ini.',
            ]);
        }
    }

    private function maybeReturnToInspection(Finding $finding): void
    {
        $application = $finding->application;

        if ($application->status !== ApplicationStatus::RevisionRequired) {
            return;
        }

        $hasOpenFindings = $application->findings()
            ->where('status', '!=', FindingStatus::Verified)
            ->exists();

        if (! $hasOpenFindings) {
            $application->update(['status' => ApplicationStatus::Inspection]);
        }
    }
}
