<?php

namespace App\Services\Inspection;

use App\Enums\Department;
use App\Enums\InspectionResultValue;
use App\Models\Finding;
use App\Models\FindingAction;
use App\Models\FindingVerification;
use App\Models\InspectionChecklist;
use App\Models\InspectionResult;
use App\Models\KimApplication;
use App\Support\PdfImage;
use App\Support\SafeFilename;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Laporan hasil pemeriksaan checklist (printout PDF) untuk satu pengajuan KIM.
 *
 * Laporan memuat seluruh fungsi pemeriksaan (HSSE, Distribusi, QQ) beserta
 * sub-butir, catatan, temuan, dan status verifikasinya.
 */
class InspectionReportService
{
    /**
     * Susun data laporan seluruh fungsi untuk satu pengajuan.
     *
     * @return array{departments: array<int, array<string, mixed>>, summary: array<string, int|float>}
     */
    public function buildReport(KimApplication $application): array
    {
        $application->loadMissing([
            'vehicle.transporter',
            'transporter',
            'inspectionResults.item.points',
            'inspectionResults.pointResults',
            'inspectionResults.inspector',
            'inspectionResults.finding',
            'inspectionResults.finding.actions' => fn ($query) => $query->latest('id'),
            'inspectionResults.finding.actions.submitter',
            'inspectionResults.finding.verifications' => fn ($query) => $query->latest('id'),
            'inspectionResults.attachments',
            'findings.creator',
        ]);

        $resultsByItem = $application->inspectionResults->keyBy('inspection_item_id');

        $checklists = InspectionChecklist::query()
            ->whereIn('department', collect(Department::inspectingDepartments())->map->value)
            ->where('is_active', true)
            ->with([
                'items' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('item_code'),
                'items.points' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order'),
            ])
            ->orderBy('code')
            ->get()
            ->sortBy(fn (InspectionChecklist $checklist) => array_search(
                $checklist->department->value,
                collect(Department::inspectingDepartments())->map->value->all(),
                true,
            ))
            ->values();

        $departments = [];
        $summary = [
            'total_items' => 0,
            'assessed_items' => 0,
            'compliant' => 0,
            'finding' => 0,
            'not_applicable' => 0,
            'open_findings' => 0,
        ];

        foreach ($checklists as $checklist) {
            $rows = [];
            $inspectors = [];

            foreach ($checklist->items as $item) {
                /** @var InspectionResult|null $result */
                $result = $resultsByItem->get($item->id);

                $summary['total_items']++;

                if ($result !== null) {
                    $summary['assessed_items']++;

                    match ($result->result) {
                        InspectionResultValue::Compliant => $summary['compliant']++,
                        InspectionResultValue::Finding => $summary['finding']++,
                        InspectionResultValue::NotApplicable => $summary['not_applicable']++,
                    };

                    if ($result->inspector !== null) {
                        $inspectorId = $result->inspector->id;

                        // Simpan waktu periksa terbaru per petugas pada fungsi ini.
                        $existingAt = $inspectors[$inspectorId]['inspected_at'] ?? null;

                        $inspectors[$inspectorId] = [
                            'name' => $result->inspector->name,
                            'jabatan' => $result->inspector->jabatan,
                            'inspected_at' => $result->inspected_at !== null
                                && ($existingAt === null || $result->inspected_at->gt($existingAt))
                                    ? $result->inspected_at
                                    : $existingAt,
                        ];
                    }

                    if ($result->finding !== null && $result->finding->blocksApproval()) {
                        $summary['open_findings']++;
                    }
                }

                $finding = $result?->finding;
                $action = $finding?->actions->first();
                $verification = $finding?->verifications->first();

                $rows[] = [
                    'item' => $item,
                    'result' => $result,
                    'checked_points' => $result?->checkedPointMap() ?? [],
                    'notes' => $result?->notes,
                    'finding' => $finding,
                    'action' => $action,
                    'verification' => $verification,
                    'follow_up' => $this->followUpStatus($finding, $action, $verification),
                    'photos' => $this->photosFor($result),
                ];
            }

            $departments[] = [
                'department' => $checklist->department,
                'checklist' => $checklist,
                'rows' => $rows,
                'inspectors' => array_values($inspectors),
                'assessed' => collect($rows)->filter(fn (array $row) => $row['result'] !== null)->count(),
                'total' => count($rows),
            ];
        }

        $summary['percentage'] = $summary['total_items'] > 0
            ? round($summary['assessed_items'] / $summary['total_items'] * 100)
            : 0.0;

        return [
            'departments' => $departments,
            'summary' => $summary,
        ];
    }

    /**
     * Ambil foto bukti pemeriksaan (data URI siap semat) untuk satu hasil.
     *
     * Hanya lampiran berupa gambar yang disertakan; PDF/bukan gambar dilewati
     * karena tabel laporan hanya menampilkan bukti visual.
     *
     * @return array<int, string>
     */
    private function photosFor(?InspectionResult $result): array
    {
        if ($result === null) {
            return [];
        }

        $disk = (string) config('media.disk');

        return $result->attachments
            ->filter(fn ($attachment) => str_starts_with((string) $attachment->mime_type, 'image/'))
            ->map(fn ($attachment) => PdfImage::dataUri(
                (string) $attachment->file_path,
                (string) $attachment->mime_type,
                $disk,
                600,
            ))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Status tindak lanjut transportir untuk sebuah temuan.
     *
     * @return array{label: string, class: string}|null
     */
    private function followUpStatus(?Finding $finding, ?FindingAction $action, ?FindingVerification $verification): ?array
    {
        if ($finding === null) {
            return null;
        }

        if ($action === null) {
            return ['label' => 'Belum ditindaklanjuti', 'class' => 'badge-finding'];
        }

        if ($verification === null) {
            return ['label' => 'Menunggu verifikasi', 'class' => 'badge-empty'];
        }

        return $verification->decision === 'accepted'
            ? ['label' => 'Tindak lanjut diterima', 'class' => 'badge-compliant']
            : ['label' => 'Tindak lanjut ditolak', 'class' => 'badge-finding'];
    }

    /**
     * Render laporan menjadi PDF.
     */
    public function render(KimApplication $application)
    {
        $report = $this->buildReport($application);

        return Pdf::loadView('reports.inspection-checklist', [
            'application' => $application,
            'departments' => $report['departments'],
            'summary' => $report['summary'],
            'printedAt' => now(),
        ])->setPaper('a4', 'portrait');
    }

    /**
     * Unduh laporan sebagai berkas PDF.
     */
    public function download(KimApplication $application)
    {
        return $this->render($application)->download($this->filename($application));
    }

    /**
     * Tampilkan laporan di browser (inline).
     */
    public function stream(KimApplication $application)
    {
        return $this->render($application)->stream($this->filename($application));
    }

    /**
     * Nama berkas laporan yang aman.
     *
     * Ditandai [ON-PROSES] bila checklist belum terisi seluruhnya, dan [DONE]
     * bila seluruh butir pada setiap checklist sudah dinilai.
     */
    public function filename(KimApplication $application): string
    {
        $tag = $this->isComplete($application) ? 'DONE' : 'ON-PROSES';

        return SafeFilename::make(
            "Laporan-Pemeriksaan-{$application->application_number}-[{$tag}].pdf",
            'laporan-pemeriksaan',
        );
    }

    /**
     * Apakah seluruh butir checklist pada pengajuan sudah dinilai.
     */
    public function isComplete(KimApplication $application): bool
    {
        $report = $this->buildReport($application);
        $summary = $report['summary'];

        return $summary['total_items'] > 0 && $summary['assessed_items'] >= $summary['total_items'];
    }
}
