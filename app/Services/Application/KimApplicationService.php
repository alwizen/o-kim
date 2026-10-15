<?php

namespace App\Services\Application;

use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\DocumentVerificationStatus;
use App\Models\DocumentType;
use App\Models\KimApplication;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KimApplicationService
{
    public function createDraft(Vehicle $vehicle, ApplicationType $type): KimApplication
    {
        $hasOpenApplication = $vehicle->kimApplications()
            ->whereIn('status', collect(ApplicationStatus::cases())
                ->filter(fn (ApplicationStatus $s) => $s->isOpen())
                ->map->value)
            ->exists();

        if ($hasOpenApplication) {
            throw ValidationException::withMessages([
                'vehicle' => 'Kendaraan ini masih memiliki pengajuan yang berjalan.',
            ]);
        }

        return DB::transaction(function () use ($vehicle, $type) {
            return KimApplication::create([
                'application_number' => $this->generateApplicationNumber(),
                'vehicle_id' => $vehicle->id,
                'transporter_id' => $vehicle->transporter_id,
                'application_type' => $type,
                'status' => ApplicationStatus::Draft,
            ]);
        });
    }

    public function submit(KimApplication $application, User $submitter): void
    {
        if (! $application->status->canTransitionTo(ApplicationStatus::Submitted)) {
            throw ValidationException::withMessages([
                'status' => 'Pengajuan tidak dapat dikirim dari status saat ini.',
            ]);
        }

        $this->ensureRequiredDocumentsComplete($application->vehicle);

        DB::transaction(function () use ($application, $submitter) {
            $application->update([
                'status' => ApplicationStatus::Submitted,
                'submitted_at' => now(),
                'submitted_by' => $submitter->id,
            ]);
        });
    }

    public function transitionTo(KimApplication $application, ApplicationStatus $target, array $extra = []): void
    {
        if (! $application->status->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => "Transisi status dari {$application->status->label()} ke {$target->label()} tidak diizinkan.",
            ]);
        }

        $application->update(array_merge(['status' => $target], $extra));
    }

    public function ensureRequiredDocumentsComplete(Vehicle $vehicle): void
    {
        $requiredTypes = DocumentType::where('is_required', true)
            ->where('is_active', true)
            ->pluck('id', 'code');

        $uploadedTypeIds = $vehicle->documents()
            ->whereNull('replaced_by_id')
            ->whereIn('document_type_id', $requiredTypes->values())
            ->where('verification_status', '!=', DocumentVerificationStatus::Rejected)
            ->pluck('document_type_id')
            ->unique();

        $missing = $requiredTypes->keys()->filter(
            fn (string $code) => ! $uploadedTypeIds->contains($requiredTypes[$code])
        );

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'documents' => 'Dokumen wajib belum lengkap: '.$missing->implode(', '),
            ]);
        }
    }

    private function generateApplicationNumber(): string
    {
        $date = now()->format('Ymd');

        return DB::transaction(function () use ($date) {
            $sequence = KimApplication::where('application_number', 'like', "APP-{$date}-%")
                ->lockForUpdate()
                ->count() + 1;

            return sprintf('APP-%s-%04d', $date, $sequence);
        });
    }
}
