<?php

namespace App\Services\Approval;

use App\Enums\ApplicationStatus;
use App\Enums\ApprovalDecision;
use App\Enums\Department;
use App\Models\Approval;
use App\Models\KimApplication;
use App\Models\User;
use App\Services\Inspection\InspectionService;
use App\Support\AppNotifier;
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

                AppNotifier::toTransporter(
                    $application->transporter_id,
                    'Pengajuan Ditolak',
                    'Pengajuan '.$application->application_number.' ditolak oleh '.$department->label().'. Alasan: '.$notes,
                    'heroicon-o-x-circle',
                    'danger',
                    '/transportir/kim-applications/'.$application->getRouteKey(),
                );
            } elseif ($department !== Department::Ftm) {
                if ($this->allFunctionalApprovalsComplete($application)) {
                    $application->update(['status' => ApplicationStatus::PendingApproval]);

                    AppNotifier::toStaffWithPermission(
                        'approve_final',
                        'Pengajuan Menunggu Approval Final',
                        $application->application_number.' siap diputuskan (3 fungsi telah menyetujui).',
                        'heroicon-o-check-badge',
                        'warning',
                        '/admin/kim-applications/'.$application->getRouteKey(),
                    );
                }
            } else {
                $application->update([
                    'status' => ApplicationStatus::Approved,
                    'final_approved_at' => now(),
                ]);

                AppNotifier::toTransporter(
                    $application->transporter_id,
                    'Pengajuan Disetujui',
                    'Pengajuan '.$application->application_number.' telah disetujui. KIM akan segera diterbitkan.',
                    'heroicon-o-check-circle',
                    'success',
                    '/transportir/kim-applications/'.$application->getRouteKey(),
                );
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
