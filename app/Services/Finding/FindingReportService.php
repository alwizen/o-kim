<?php

namespace App\Services\Finding;

use App\Enums\FindingStatus;
use App\Models\BeritaAcaraDocument;
use App\Models\Finding;
use App\Models\FindingActionAttachment;
use App\Models\InspectionResultAttachment;
use App\Models\KimApplication;
use App\Support\SafeFilename;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Berita Acara (BA) hasil pemeriksaan tindak lanjut temuan mobil tangki.
 *
 * Dokumen hanya diterbitkan bila minimal satu temuan sudah ditindaklanjuti
 * transportir dan diverifikasi (status "verified"). Halaman pertama memuat
 * ringkasan temuan, halaman kedua lampiran dokumentasi foto temuan awal
 * berdampingan dengan foto verifikasi tindak lanjut beserta tanggalnya.
 */
class FindingReportService
{
    /** Batas lebar gambar di PDF (px) agar berkas tidak membengkak. */
    private const EMBED_MAX_PX = 900;

    private function disk(): string
    {
        return config('media.disk');
    }

    /**
     * Apakah pengajuan sudah layak memiliki Berita Acara.
     *
     * KIM sementara (dispensasi aktif) tidak menerbitkan BA.
     */
    public function isEligible(KimApplication $application): bool
    {
        if ($application->hasActiveDispensation()) {
            return false;
        }

        return $this->verifiedFindings($application)->isNotEmpty();
    }

    /**
     * Temuan yang sudah ditindaklanjuti dan diverifikasi.
     *
     * @return Collection<int, Finding>
     */
    private function verifiedFindings(KimApplication $application)
    {
        $application->loadMissing([
            'vehicle',
            'transporter',
            'findings' => fn ($query) => $query->where('status', FindingStatus::Verified)->orderBy('id'),
            'findings.inspectionResult.attachments',
            'findings.actions.attachments',
            'findings.verifications',
        ]);

        return $application->findings
            ->where('status', FindingStatus::Verified)
            ->values();
    }

    /**
     * Susun data Berita Acara untuk satu pengajuan.
     *
     * @return array{
     *     application: KimApplication,
     *     rows: array<int, array{no: int, description: string, status: string, finding: Finding}>,
     *     attachments: array<int, array<string, mixed>>
     * }
     */
    public function buildReport(KimApplication $application): array
    {
        $findings = $this->verifiedFindings($application);

        $rows = [];
        $attachments = [];

        foreach ($findings as $index => $finding) {
            /** @var Finding $finding */
            $rows[] = [
                'no' => $index + 1,
                'description' => $finding->description,
                'status' => 'Sudah ditindaklanjuti',
                'finding' => $finding,
            ];

            $attachments[] = $this->buildAttachmentRow($finding, $index + 1);
        }

        return [
            'application' => $application,
            'rows' => $rows,
            'attachments' => $attachments,
        ];
    }

    /**
     * Susun satu baris lampiran: foto temuan awal (kiri) dan foto tindak lanjut (kanan).
     *
     * @return array<string, mixed>
     */
    private function buildAttachmentRow(Finding $finding, int $no): array
    {
        $initialAttachments = $finding->inspectionResult?->attachments ?? collect();
        $actionAttachments = $finding->actions
            ->flatMap(fn ($action) => $action->attachments)
            ->values();

        $initialImage = $this->firstImage($initialAttachments);
        $actionImage = $this->firstImage($actionAttachments);

        $initialDate = $finding->inspectionResult?->inspected_at
            ?? $finding->created_at;

        $lastVerification = $finding->verifications->last();
        $actionDate = $lastVerification?->verified_at
            ?? $finding->actions->last()?->created_at;

        return [
            'no' => $no,
            'description' => $finding->description,
            'initial_image' => $initialImage === null ? null : $this->imageDataUri($initialImage),
            'initial_date' => $initialDate,
            'action_image' => $actionImage === null ? null : $this->imageDataUri($actionImage),
            'action_date' => $actionDate,
        ];
    }

    /**
     * Ambil lampiran gambar pertama dari kumpulan lampiran.
     *
     * @param  Collection<int, mixed>  $attachments
     */
    private function firstImage($attachments): InspectionResultAttachment|FindingActionAttachment|null
    {
        return $attachments->first(
            fn ($attachment) => str_starts_with((string) $attachment->mime_type, 'image/')
        );
    }

    /**
     * Ubah berkas gambar pada media disk menjadi data URI yang dapat dibaca DomPDF.
     *
     * DomPDF belum mendukung WebP, sehingga gambar WebP dikonversi ke PNG lebih
     * dulu. Bila konversi gagal (mis. GD tanpa dukungan WebP) gambar dilewati
     * agar dokumen tetap dapat tercetak.
     */
    private function imageDataUri(InspectionResultAttachment|FindingActionAttachment $attachment): ?string
    {
        $storage = Storage::disk($this->disk());

        if (! $storage->exists($attachment->file_path)) {
            return null;
        }

        $binary = $storage->get($attachment->file_path);

        if ($binary === null || $binary === '') {
            return null;
        }

        $mime = (string) $attachment->mime_type;

        // DomPDF hanya mengenali JPEG dan PNG dengan aman.
        if (! in_array($mime, ['image/jpeg', 'image/png'], true)) {
            $binary = $this->convertToPng($binary);

            if ($binary === null) {
                return null;
            }

            $mime = 'image/png';
        }

        return 'data:'.$mime.';base64,'.base64_encode($binary);
    }

    /**
     * Konversi biner gambar (mis. WebP) ke PNG dan kecilkan bila sangat besar.
     */
    private function convertToPng(string $binary): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $image = @imagecreatefromstring($binary);

        if ($image === false) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if ($width > self::EMBED_MAX_PX || $height > self::EMBED_MAX_PX) {
            $scale = self::EMBED_MAX_PX / max($width, $height);
            $target = imagescale($image, (int) round($width * $scale), (int) round($height * $scale));

            if ($target !== false) {
                $image = $target;
            }
        }

        ob_start();
        imagepng($image, null, 6);
        $png = ob_get_clean();

        return is_string($png) && $png !== '' ? $png : null;
    }

    /**
     * Render Berita Acara menjadi PDF.
     *
     * Bila `$signatures` diberikan, blok tanda tangan elektronik ikut dirender
     * (dokumen final). Tanpa `$signatures`, dokumen berisi isi temuan saja
     * (pratinjau sebelum approval lengkap).
     *
     * @param  array<int, array<string, mixed>>  $signatures
     */
    public function render(KimApplication $application, array $signatures = [], ?BeritaAcaraDocument $document = null)
    {
        $report = $this->buildReport($application);

        return Pdf::loadView('reports.berita-acara', [
            'application' => $report['application'],
            'rows' => $report['rows'],
            'attachments' => $report['attachments'],
            'printedAt' => now(),
            'signatureBlocks' => $signatures,
            'document' => $document,
        ])->setPaper('a4', 'portrait');
    }

    /**
     * Unduh Berita Acara sebagai berkas PDF.
     */
    public function download(KimApplication $application)
    {
        return $this->render($application)->download($this->filename($application));
    }

    /**
     * Tampilkan Berita Acara di browser (inline).
     */
    public function stream(KimApplication $application)
    {
        return $this->render($application)->stream($this->filename($application));
    }

    /**
     * Nama berkas Berita Acara yang aman.
     */
    public function filename(KimApplication $application): string
    {
        return SafeFilename::make(
            "Berita-Acara-{$application->application_number}.pdf",
            'berita-acara',
        );
    }
}
