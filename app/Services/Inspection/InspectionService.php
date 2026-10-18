<?php

namespace App\Services\Inspection;

use App\Enums\ApplicationStatus;
use App\Enums\Department;
use App\Enums\FindingStatus;
use App\Enums\InspectionResultValue;
use App\Models\Finding;
use App\Models\InspectionItem;
use App\Models\InspectionResult;
use App\Models\KimApplication;
use App\Models\User;
use App\Notifications\FindingCreated;
use App\Support\AppNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InspectionService
{
    /**
     * Catat hasil pemeriksaan satu butir checklist oleh petugas fungsi tertentu.
     *
     * @param  array<int, int|string>  $pointIds  ID sub-butir yang dicentang
     */
    public function recordResult(
        KimApplication $application,
        InspectionItem $item,
        InspectionResultValue $result,
        User $inspector,
        ?string $notes = null,
        ?string $findingDescription = null,
        array $pointIds = [],
    ): InspectionResult {
        $department = $item->checklist->department;

        if (! $inspector->can($department->inspectPermission())) {
            throw ValidationException::withMessages([
                'inspector' => 'Anda tidak berwenang memeriksa fungsi '.$department->label().'.',
            ]);
        }

        if (! $item->is_active || ! $item->checklist->is_active) {
            throw ValidationException::withMessages([
                'item' => 'Butir checklist ini tidak aktif dan tidak dapat dinilai.',
            ]);
        }

        if ($application->status !== ApplicationStatus::Inspection
            && $application->status !== ApplicationStatus::Submitted) {
            throw ValidationException::withMessages([
                'status' => 'Pengajuan tidak sedang dalam tahap pemeriksaan.',
            ]);
        }

        if (! $application->isInspectionDue()) {
            throw ValidationException::withMessages([
                'schedule' => 'Pemeriksaan belum dapat dilakukan: jadwal belum ditetapkan atau tanggalnya belum tiba.',
            ]);
        }

        if ($result === InspectionResultValue::Finding && blank($findingDescription)) {
            throw ValidationException::withMessages([
                'finding' => 'Deskripsi temuan wajib diisi jika hasil pemeriksaan adalah temuan.',
            ]);
        }

        return DB::transaction(function () use ($application, $item, $result, $inspector, $notes, $findingDescription, $department, $pointIds) {
            if ($application->status === ApplicationStatus::Submitted) {
                $application->update(['status' => ApplicationStatus::Inspection]);
            }

            $inspectionResult = InspectionResult::updateOrCreate(
                [
                    'kim_application_id' => $application->id,
                    'inspection_item_id' => $item->id,
                ],
                [
                    'inspected_by' => $inspector->id,
                    'result' => $result,
                    'notes' => $notes,
                    'inspected_at' => now(),
                ]
            );

            $this->syncPoints($inspectionResult, $item, $pointIds);

            if ($result === InspectionResultValue::Finding && ! $inspectionResult->finding) {
                Finding::create([
                    'kim_application_id' => $application->id,
                    'inspection_result_id' => $inspectionResult->id,
                    'department' => $department,
                    'description' => $findingDescription,
                    'status' => FindingStatus::Open,
                    'created_by' => $inspector->id,
                ]);

                if ($application->status !== ApplicationStatus::RevisionRequired) {
                    $application->update(['status' => ApplicationStatus::RevisionRequired]);
                }

                DB::afterCommit(fn () => AppNotifier::notifyTransporter(
                    $application->transporter_id,
                    new FindingCreated(
                        $application->application_number,
                        $department->label(),
                        $findingDescription,
                    ),
                ));
            }

            return $inspectionResult;
        });
    }

    /**
     * Sinkronkan status centang sub-butir untuk satu hasil pemeriksaan.
     * Hanya sub-butir milik butir ini yang boleh disimpan (mencegah ID dari client).
     *
     * @param  array<int, int|string>  $pointIds
     */
    private function syncPoints(InspectionResult $inspectionResult, InspectionItem $item, array $pointIds): void
    {
        $validPointIds = $item->points()->pluck('id')->all();

        if (empty($validPointIds)) {
            return;
        }

        $selected = collect($pointIds)
            ->map(fn ($id) => (int) $id)
            ->intersect($validPointIds)
            ->all();

        foreach ($validPointIds as $pointId) {
            $inspectionResult->pointResults()->updateOrCreate(
                ['inspection_item_point_id' => $pointId],
                ['is_checked' => in_array($pointId, $selected, true)]
            );
        }
    }

    /**
     * Periksa apakah seluruh checklist wajib untuk suatu fungsi sudah selesai.
     */
    public function isDepartmentInspectionComplete(KimApplication $application, Department $department): bool
    {
        $requiredItemIds = InspectionItem::whereHas('checklist', function ($query) use ($department) {
            $query->where('department', $department->value)->where('is_active', true);
        })->where('is_active', true)->where('is_required', true)->pluck('id');

        if ($requiredItemIds->isEmpty()) {
            return true;
        }

        $completedCount = $application->inspectionResults()
            ->whereIn('inspection_item_id', $requiredItemIds)
            ->count();

        return $completedCount >= $requiredItemIds->count();
    }

    /**
     * Pengajuan siap masuk tahap approval bila seluruh fungsi selesai dan tidak ada temuan terbuka.
     */
    public function isReadyForApproval(KimApplication $application): bool
    {
        foreach (Department::inspectingDepartments() as $department) {
            if (! $this->isDepartmentInspectionComplete($application, $department)) {
                return false;
            }
        }

        return ! $application->findings()
            ->where('status', '!=', FindingStatus::Verified)
            ->exists();
    }
}
