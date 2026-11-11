<?php

namespace App\Services\Document;

use App\Enums\ApprovalDecision;
use App\Enums\Department;
use App\Models\DocumentSignature;
use App\Models\KimApplication;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Snapshot tanda tangan elektronik lima fungsi (HSSE, Distribusi, QQ, RSD, FTM)
 * dari data approval pengajuan.
 *
 * Dipakai bersama oleh Berita Acara dan Kartu KIM agar mekanisme tanda tangan
 * dan QR verifikasi konsisten. Setiap tanda tangan menyimpan salinan nama,
 * jabatan, waktu approval, IP, dan user agent agar tetap historis.
 */
class DocumentSignatureService
{
    /**
     * Buat tanda tangan untuk sebuah dokumen (BA atau KIM).
     *
     * KIM sementara (dispensasi aktif) hanya ditandatangani FTM.
     *
     * @param  Model  $signable  BeritaAcaraDocument|KimCard
     * @return array<int, DocumentSignature>
     */
    public function createFor(Model $signable, KimApplication $application): array
    {
        $departments = $application->hasActiveDispensation()
            ? [Department::Ftm]
            : Department::approvalSteps();

        $signatures = [];

        foreach ($departments as $index => $department) {
            $approval = $application->approvals()
                ->with('approver')
                ->where('department', $department->value)
                ->where('decision', ApprovalDecision::Approved->value)
                ->latest('id')
                ->first();

            // Data lama: RSD belum ada saat pengajuan disetujui FTM.
            if ($approval === null) {
                continue;
            }

            $signatures[] = $signable->signatures()->create([
                'department' => $department,
                'user_id' => $approval->approved_by,
                'name' => $approval->approver?->name ?? '—',
                'jabatan' => $approval->approver?->jabatan,
                'decided_at' => $approval->decided_at,
                'ip_address' => $approval->ip_address,
                'user_agent' => $approval->user_agent,
                'qr_token' => Str::random(48),
                'sort_order' => $index,
            ]);
        }

        return $signatures;
    }

    /**
     * Pastikan dokumen memiliki tanda tangan. Dipakai untuk backfill dokumen
     * lama (BA/KIM yang dibuat sebelum fitur tanda tangan ada).
     *
     * @param  Model  $signable  BeritaAcaraDocument|KimCard
     */
    public function ensureFor(Model $signable, KimApplication $application): void
    {
        if ($signable->signatures()->exists()) {
            return;
        }

        $this->createFor($signable, $application);
    }

    /**
     * Susun blok tanda tangan untuk dirender (QR opsional bila toggle dimatikan).
     *
     * @param  Collection<int, DocumentSignature>  $signatures
     * @param  array<int, Department>  $departments
     * @return array<int, array{signature: DocumentSignature|null, department: Department, qr: string|null, placeholder: bool}>
     */
    public function blocks(Collection $signatures, array $departments, bool $qrEnabled): array
    {
        $byDepartment = $signatures->keyBy(fn (DocumentSignature $signature) => $signature->department->value);

        $blocks = [];

        foreach ($departments as $department) {
            /** @var DocumentSignature|null $signature */
            $signature = $byDepartment->get($department->value);

            if ($signature === null) {
                $blocks[] = [
                    'signature' => null,
                    'department' => $department,
                    'qr' => null,
                    'placeholder' => true,
                ];

                continue;
            }

            $blocks[] = [
                'signature' => $signature,
                'department' => $department,
                'qr' => $qrEnabled ? app(DocumentSignatureQrService::class)->renderDataUri($signature, 120) : null,
                'placeholder' => false,
            ];
        }

        return $blocks;
    }
}
