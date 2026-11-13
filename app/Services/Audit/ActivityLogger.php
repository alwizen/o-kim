<?php

namespace App\Services\Audit;

use App\Models\ActivityLog;
use App\Models\DocumentType;
use App\Models\InspectionChecklist;
use App\Models\KimApplication;
use App\Models\KimCard;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Pencatat tunggal jejak aktivitas pengguna.
 *
 * Dipakai dari controller, service, dan aksi agar format log konsisten:
 * pelaku, waktu, IP, user agent, objek terkait, dan deskripsi manusiawi.
 */
class ActivityLogger
{
    /**
     * Catat satu aktivitas.
     *
     * @param  array<string, mixed>  $properties  Data tambahan spesifik event.
     * @param  string|null  $label  Label objek (mis. nomor pengajuan) bila subject tidak punya label baku.
     */
    public function log(
        string $event,
        ?Model $subject = null,
        string $description = '',
        array $properties = [],
        ?string $label = null,
        ?User $actor = null,
        string $status = ActivityLog::STATUS_SUCCESS,
    ): ActivityLog {
        $actor ??= auth()->user();

        return ActivityLog::create([
            'user_id' => $actor?->id,
            'user_name' => $actor?->name ?? 'Tamu',
            'user_role' => $actor?->getRoleNames()->first(),
            'event' => $event,
            'status' => $status,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'subject_label' => $label ?? $this->defaultLabel($subject),
            'description' => $description !== '' ? $description : $event,
            'properties' => $properties === [] ? null : $properties,
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }

    /**
     * Catat aksi yang gagal beserta alasannya.
     *
     * Kegagalan tidak mengubah state, sehingga tanpa catatan ini penyebabnya
     * hilang setelah notifikasi merah ditutup.
     *
     * @param  array<string, mixed>  $properties
     */
    public function failure(
        string $event,
        ?Model $subject = null,
        string $reason = '',
        array $properties = [],
        ?string $label = null,
        ?User $actor = null,
    ): ActivityLog {
        $actor ??= auth()->user();

        return ActivityLog::create([
            'user_id' => $actor?->id,
            'user_name' => $actor?->name ?? 'Tamu',
            'user_role' => $actor?->getRoleNames()->first(),
            'event' => $event,
            'status' => ActivityLog::STATUS_FAILED,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'subject_label' => $label ?? $this->defaultLabel($subject),
            'description' => $reason !== '' ? $reason : 'Aksi gagal.',
            'properties' => $properties === [] ? null : $properties,
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }

    /**
     * Catat kegagalan validasi (mis. approval ditolak karena checklist belum lengkap).
     *
     * @param  array<string, mixed>  $extra
     */
    public function failureFromValidation(
        string $event,
        ?Model $subject,
        ValidationException $exception,
        array $extra = [],
        ?string $label = null,
    ): ActivityLog {
        $reason = $this->firstError($exception);

        return $this->failure(
            $event,
            $subject,
            $reason,
            $extra + ['error' => $reason],
            $label,
        );
    }

    /**
     * Ambil pesan error pertama dari ValidationException.
     */
    public function firstError(ValidationException $exception): string
    {
        return (string) (collect($exception->errors())->flatten()->first() ?? 'Aksi gagal.');
    }

    /**
     * Catat aktivitas hanya sekali untuk kombinasi event + subject.
     *
     * Dipakai untuk event yang bisa terpicu berulang dalam satu siklus
     * (mis. checklist fungsi diisi ulang saat revisi).
     *
     * @param  array<string, mixed>  $properties
     */
    public function logOnce(
        string $event,
        ?Model $subject = null,
        string $description = '',
        array $properties = [],
        ?string $label = null,
        ?User $actor = null,
    ): ?ActivityLog {
        $exists = ActivityLog::query()
            ->where('event', $event)
            ->when(
                $subject !== null,
                fn ($query) => $query->where('subject_type', $subject->getMorphClass())
                    ->where('subject_id', $subject->getKey()),
            )
            ->exists();

        if ($exists) {
            return null;
        }

        return $this->log($event, $subject, $description, $properties, $label, $actor);
    }

    /**
     * Label bawaan untuk objek yang dikenal aplikasi.
     */
    private function defaultLabel(?Model $subject): ?string
    {
        if ($subject === null) {
            return null;
        }

        return match (true) {
            $subject instanceof KimApplication => $subject->application_number,
            $subject instanceof KimCard => $subject->kim_number,
            $subject instanceof Vehicle => $subject->registration_number,
            $subject instanceof VehicleDocument => $subject->documentType?->name,
            $subject instanceof DocumentType,
            $subject instanceof InspectionChecklist => $subject->name ?? (string) $subject->getKey(),
            default => method_exists($subject, 'getAttribute') && $subject->getAttribute('name')
                ? (string) $subject->getAttribute('name')
                : null,
        };
    }
}
