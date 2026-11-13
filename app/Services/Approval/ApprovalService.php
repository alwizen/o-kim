<?php

namespace App\Services\Approval;

use App\Enums\ApplicationStatus;
use App\Enums\ApprovalDecision;
use App\Enums\Department;
use App\Models\Approval;
use App\Models\KimApplication;
use App\Models\User;
use App\Notifications\ApplicationApproved;
use App\Notifications\ApplicationApprovedByDepartment;
use App\Notifications\ApplicationPendingDepartmentApproval;
use App\Notifications\ApplicationPendingFinalApproval;
use App\Notifications\ApplicationRejected;
use App\Services\Audit\ActivityLogger;
use App\Services\Finding\BeritaAcaraService;
use App\Services\Inspection\InspectionService;
use App\Support\AppNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
            // Kunci baris pengajuan agar keputusan approval bersifat serial
            // (mencegah dua approval ganda pada request bersamaan).
            $application = KimApplication::lockForUpdate()->findOrFail($application->id);

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
                // KIM sementara (ada temuan dispensasi aktif) cukup disetujui FTM;
                // approval pengawas dan RSD tidak diperlukan karena temuan sudah didispensasi.
                if (! $application->hasActiveDispensation()) {
                    $this->ensurePreFinalApprovalsComplete($application);
                }

                $this->ensureInspectionReady($application);
            }

            if ($department === Department::Rsd) {
                // RSD hanya menjadi gerbang setelah ketiga fungsi menyetujui.
                if ($application->hasActiveDispensation()) {
                    throw ValidationException::withMessages([
                        'approval' => 'KIM sementara cukup approval FTM; approval RSD tidak diperlukan.',
                    ]);
                }

                $this->ensureFunctionalApprovalsComplete($application);
            }

            if ($decision === ApprovalDecision::Approved && $department !== Department::Ftm) {
                $blockers = $this->inspectionService->approvalBlockers($application);

                if ($blockers !== []) {
                    throw ValidationException::withMessages([
                        'readiness' => 'Pengajuan belum siap: '.implode(' ', $blockers),
                    ]);
                }
            }

            $approval = Approval::create([
                'kim_application_id' => $application->id,
                'department' => $department,
                'decision' => $decision,
                'approved_by' => $approver->id,
                'notes' => $notes,
                'ip_address' => request()->ip(),
                'user_agent' => Str::limit((string) request()->userAgent(), 500, ''),
                'decided_at' => now(),
            ]);

            if ($decision === ApprovalDecision::Rejected) {
                $application->update([
                    'status' => ApplicationStatus::Rejected,
                    'rejection_reason' => '['.$department->label().'] '.$notes,
                ]);

                DB::afterCommit(fn () => AppNotifier::notifyTransporter(
                    $application->transporter_id,
                    new ApplicationRejected(
                        $application->application_number,
                        $department->label(),
                        (string) $notes,
                    ),
                ));
            } elseif ($department !== Department::Ftm) {
                DB::afterCommit(fn () => AppNotifier::notifyTransporter(
                    $application->transporter_id,
                    new ApplicationApprovedByDepartment(
                        $application->application_number,
                        $department->label(),
                    ),
                ));

                if ($department !== Department::Rsd && $this->allFunctionalApprovalsComplete($application)) {
                    $application->update(['status' => ApplicationStatus::PendingApproval]);

                    if ($application->hasActiveDispensation()) {
                        // KIM sementara: temuan sudah didispensasi, FTM langsung memutuskan.
                        DB::afterCommit(fn () => AppNotifier::notifyStaffWithPermission(
                            'approve_final',
                            new ApplicationPendingFinalApproval(
                                $application->application_number,
                                $application->vehicle->registration_number,
                            ),
                        ));
                    } else {
                        // Ketiga fungsi selesai: antrean berpindah ke Pengawas RSD
                        // (wakil FTM) sebelum approval final.
                        DB::afterCommit(fn () => AppNotifier::notifyStaffWithPermission(
                            'approve_rsd',
                            new ApplicationPendingDepartmentApproval(
                                $application->application_number,
                                $application->vehicle->registration_number,
                                Department::Rsd->label(),
                                (string) $application->getRouteKey(),
                            ),
                        ));
                    }
                } elseif ($department === Department::Rsd) {
                    // RSD menyetujui: pengajuan menunggu approval final FTM.
                    DB::afterCommit(fn () => AppNotifier::notifyStaffWithPermission(
                        'approve_final',
                        new ApplicationPendingFinalApproval(
                            $application->application_number,
                            $application->vehicle->registration_number,
                        ),
                    ));
                }
            } else {
                $application->update([
                    'status' => ApplicationStatus::Approved,
                    'final_approved_at' => now(),
                ]);

                DB::afterCommit(fn () => AppNotifier::notifyTransporter(
                    $application->transporter_id,
                    new ApplicationApproved(
                        $application->application_number,
                        $application->vehicle->registration_number,
                    ),
                ));

                // Setelah approval final lengkap, bekukan Berita Acara (bila layak).
                DB::afterCommit(fn () => app(BeritaAcaraService::class)
                    ->finalizeIfReady($application->fresh()));
            }

            app(ActivityLogger::class)->log(
                'approval.'.($decision === ApprovalDecision::Approved ? 'approved' : 'rejected'),
                $application,
                $approver->name.' '.($decision === ApprovalDecision::Approved ? 'menyetujui' : 'menolak')
                    .' tahap '.$department->label().' pada pengajuan '.$application->application_number.'.',
                [
                    'department' => $department->value,
                    'decision' => $decision->value,
                    'notes' => $notes,
                ],
            );

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

    /**
     * Seluruh approval pra-final selesai: tiga fungsi pemeriksa plus Pengawas RSD.
     */
    public function allPreFinalApprovalsComplete(KimApplication $application): bool
    {
        $departments = Department::approvableDepartments();

        $approved = $application->approvals()
            ->whereIn('department', collect($departments)->map->value)
            ->where('decision', ApprovalDecision::Approved)
            ->pluck('department')
            ->unique();

        return $approved->count() === count($departments);
    }

    public function rsdApprovalComplete(KimApplication $application): bool
    {
        return $application->approvals()
            ->where('department', Department::Rsd->value)
            ->where('decision', ApprovalDecision::Approved)
            ->exists();
    }

    /**
     * Approval final tetap menunggu temuan selesai/didispensasi dan seluruh checklist terisi.
     */
    private function ensureInspectionReady(KimApplication $application): void
    {
        if (! $this->inspectionService->isReadyForApproval($application)) {
            throw ValidationException::withMessages([
                'findings' => 'Masih ada temuan atau butir pemeriksaan yang belum selesai.',
            ]);
        }
    }

    private function ensurePreFinalApprovalsComplete(KimApplication $application): void
    {
        if (! $this->allFunctionalApprovalsComplete($application)) {
            throw ValidationException::withMessages([
                'approval' => 'FTM hanya dapat menyetujui setelah HSSE, Distribusi, dan QQ menyetujui.',
            ]);
        }

        if (! $this->rsdApprovalComplete($application)) {
            throw ValidationException::withMessages([
                'approval' => 'FTM hanya dapat menyetujui setelah Pengawas RSD menyetujui.',
            ]);
        }
    }

    private function ensureFunctionalApprovalsComplete(KimApplication $application): void
    {
        if (! $this->allFunctionalApprovalsComplete($application)) {
            throw ValidationException::withMessages([
                'approval' => 'Pengawas RSD hanya dapat menyetujui setelah HSSE, Distribusi, dan QQ menyetujui.',
            ]);
        }
    }
}
