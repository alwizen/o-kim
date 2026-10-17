<?php

namespace App\Services\Application;

use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\DocumentVerificationStatus;
use App\Models\DocumentType;
use App\Models\KimApplication;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\KimApplicationSubmitted;
use App\Services\Setting\SettingsService;
use App\Support\AppNotifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
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

        $this->notifyStaffOfSubmission($application);
    }

    private function notifyStaffOfSubmission(KimApplication $application): void
    {
        AppNotifier::notifyStaffWithPermission(
            'view_applications',
            new KimApplicationSubmitted(
                $application->application_number,
                $application->vehicle->registration_number,
                $application->transporter->company_name,
            ),
        );
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
        $missing = $this->missingRequiredDocumentCodes($vehicle);

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'documents' => 'Dokumen wajib belum lengkap atau belum diverifikasi: '.$missing->implode(', '),
            ]);
        }
    }

    /**
     * Kode jenis dokumen wajib yang belum ada / belum terverifikasi pada kendaraan.
     *
     * @return Collection<int, string>
     */
    public function missingRequiredDocumentCodes(Vehicle $vehicle): Collection
    {
        $requiredTypes = DocumentType::where('is_required', true)
            ->where('is_active', true)
            ->pluck('id', 'code');

        $verifiedTypeIds = $vehicle->documents()
            ->whereNull('replaced_by_id')
            ->whereIn('document_type_id', $requiredTypes->values())
            ->where('verification_status', DocumentVerificationStatus::Verified)
            ->pluck('document_type_id')
            ->unique();

        return $requiredTypes->keys()->filter(
            fn (string $code) => ! $verifiedTypeIds->contains($requiredTypes[$code])
        )->values();
    }

    /**
     * Kendaraan siap diajukan? Seluruh dokumen wajib sudah terverifikasi
     * dan tidak ada pengajuan berjalan.
     */
    public function isReadyForApplication(Vehicle $vehicle): bool
    {
        if ($this->missingRequiredDocumentCodes($vehicle)->isNotEmpty()) {
            return false;
        }

        return ! $vehicle->kimApplications()
            ->whereIn('status', collect(ApplicationStatus::cases())
                ->filter(fn (ApplicationStatus $s) => $s->isOpen())
                ->map->value)
            ->exists();
    }

    /**
     * Query kendaraan yang siap diajukan untuk daftar pilihan transportir.
     *
     * @param  array<int, int|string>  $transporterIds
     * @return Builder<Vehicle>
     */
    public function readyVehiclesQuery(array $transporterIds): Builder
    {
        $requiredTypeIds = DocumentType::where('is_required', true)
            ->where('is_active', true)
            ->pluck('id');

        $openStatuses = collect(ApplicationStatus::cases())
            ->filter(fn (ApplicationStatus $s) => $s->isOpen())
            ->map->value
            ->all();

        return Vehicle::query()
            ->whereIn('transporter_id', $transporterIds)
            ->whereDoesntHave('kimApplications', fn ($query) => $query->whereIn('status', $openStatuses))
            ->when($requiredTypeIds->isNotEmpty(), function ($query) use ($requiredTypeIds) {
                foreach ($requiredTypeIds as $typeId) {
                    $query->whereHas('documents', fn ($documentQuery) => $documentQuery
                        ->whereNull('replaced_by_id')
                        ->where('document_type_id', $typeId)
                        ->where('verification_status', DocumentVerificationStatus::Verified));
                }
            });
    }

    private function generateApplicationNumber(): string
    {
        $settings = app(SettingsService::class);

        $segments = [trim($settings->applicationNumberPrefix(), '/')];

        $periodFormat = $settings->applicationNumberPeriodFormat();

        if ($periodFormat !== '' && $periodFormat !== 'none') {
            $segments[] = now()->format($periodFormat);
        }

        $prefix = implode('/', $segments).'/';

        return DB::transaction(function () use ($prefix, $settings) {
            $last = KimApplication::where('application_number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('application_number')
                ->value('application_number');

            $digits = max($settings->applicationNumberSequenceDigits(), 1);
            $sequence = $last ? ((int) substr($last, -$digits)) + 1 : 1;

            return $prefix.str_pad((string) $sequence, $digits, '0', STR_PAD_LEFT);
        });
    }
}
