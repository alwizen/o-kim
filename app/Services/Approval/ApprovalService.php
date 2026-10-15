<?php

namespace App\Services\Approval;

use App\Enums\ApplicationStatus;
use App\Enums\ApprovalDecision;
use App\Enums\Department;
use App\Models\Approval;
use App\Models\KimApplication;
use App\Models\User;
use App\Services\Inspection\InspectionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovalService
{
    public function __construct(private InspectionService $inspectionService) {}

    public function decide(
        KimApplication $application,
        Department $department,
        ApprovalDecision $decision,
        User $approver,
        ?string $notes = null,
    ): Approval {
        $permission = $department === Department::Ftm ? 'approve_final' : 'approve_'.$department->value;

        if (! $approver->can($permission)) {
            throw ValidationException::withMessages([
                'approver' => 'Anda tidak berwenang memberikan approval untuk '.$department->label().'.',
            ]);
        }

        if ($application->status !== ApplicationStatus::PendingApproval
            && $application->status !== ApplicationStatus::Inspection
            && $application->status !== ApplicationStatus::RevisionRequired) {
            throw ValidationException::withMessages([
                'status' => 'Pengajuan tidak dalam tahap yang dapat di-approve.',
            ]);
        }

        if ($decision === ApprovalDecision::Rejected && blank($notes)) {
            throw ValidationException::withMessages([
                'notes' => 'Penolakan approval wajib disertai alasan.',
            ]);
        }

        return DB::transaction(function () use ($application, $department, $decision, $approver, $notes) {
            // Cegah duplikasi approval aktif untuk tahapan yang sama
            $existing = $application->approvals()
                ->where('department', $department->value)
                ->latest('id')
                ->first();

            if ($existing && $existing->decision === ApprovalDecision::Approved) {
                throw ValidationException::withMessages([
                    'approval' => $department->label().' sudah memberikan approval.',
                ]);
            }

            if ($department === Department::Ftm) {
                $this->ensureFunctionalApprovalsComplete($application);
            }

            if ($decision === ApprovalDecision::Approved && $department !== Department::Ftm) {
                if (! $this->inspectionService->isReadyForApproval($application)) {
                    throw ValidationException::withMessages([
                        'readiness' => 'Pengajuan belum siap: checklist belum lengkap atau temuan masih terbuka.',
                    ]);
                }
            }

            $approval = Approval::create([
                'kim_application_id' => $application->id,
                'department' => $department,
                'decision' => $decision,
                'approved_by' => $approver->id,
                'notes' => $notes,
                'decided_at' => now(),
            ]);

            if ($decision === ApprovalDecision::Rejected) {
                $application->update([
                    'status' => ApplicationStatus::Rejected,
                    'rejection_reason' => '['.$department->label().'] '.$notes,
                ]);
            } elseif ($department !== Department::Ftm) {
                if ($this->allFunctionalApprovalsComplete($application)) {
                    $application->update(['status' => ApplicationStatus::PendingApproval]);
                }
            } else {
                $application->update([
                    'status' => ApplicationStatus::Approved,
                    'final_approved_at' => now(),
                ]);
            }

            return $approval;
        });
    }

    public function allFunctionalApprovalsComplete(KimApplication $application): bool
    {
        $approved = $application->approvals()
            ->whereIn('department', collect(Department::inspectingDepartments())->map->value)
            ->where('decision', ApprovalDecision::Approved)
            ->pluck('department')
            ->unique();

        return $approved->count() === count(Department::inspectingDepartments());
    }

    private function ensureFunctionalApprovalsComplete(KimApplication $application): void
    {
        if (! $this->allFunctionalApprovalsComplete($application)) {
            throw ValidationException::withMessages([
                'approval' => 'FTM hanya dapat menyetujui setelah HSSE, Distribusi, dan QQ menyetujui.',
            ]);
        }
    }
}
