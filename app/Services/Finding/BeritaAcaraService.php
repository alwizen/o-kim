<?php

namespace App\Services\Finding;

use App\Enums\ApprovalDecision;
use App\Enums\Department;
use App\Models\BeritaAcaraDocument;
use App\Models\KimApplication;
use App\Services\Audit\ActivityLogger;
use App\Services\Document\DocumentSignatureService;
use App\Services\Setting\SettingsService;
use App\Support\SafeFilename;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Berita Acara (BA) hasil pemeriksaan tindak lanjut temuan beserta blok
 * tanda tangan elektronik lima pengawas (HSSE, Distribusi, QQ, RSD, FTM).
 *
 * BA final hanya dibekukan setelah seluruh approval inti tersedia. Setelah
 * dibekukan, berkas PDF disimpan dan di-hash (SHA-256) sehingga isinya tidak
 * berubah; setiap tanda tangan memiliki token QR untuk halaman verifikasi
 * publik.
 */
class BeritaAcaraService
{
    public function __construct(
        private FindingReportService $reportService,
        private DocumentSignatureService $signatureService,
    ) {}

    private function disk(): string
    {
        return config('media.disk');
    }

    /**
     * Apakah pengajuan layak memiliki Berita Acara.
     *
     * KIM sementara (dispensasi aktif) tidak menerbitkan BA; tanda tangan FTM
     * cukup pada kartu KIM sementara.
     */
    public function isEligible(KimApplication $application): bool
    {
        return $this->reportService->isEligible($application);
    }

    /**
     * Approval inti sudah lengkap sehingga blok tanda tangan dapat dibekukan.
     *
     * HSSE, Distribusi, QQ, dan FTM wajib menyetujui. RSD ikut bila tersedia;
     * data lama yang disetujui sebelum role RSD dibuat tetap final.
     */
    public function isReadyForSignatures(KimApplication $application): bool
    {
        $required = [Department::Hsse, Department::Distribusi, Department::Qq, Department::Ftm];

        foreach ($required as $department) {
            $approved = $application->approvals()
                ->where('department', $department->value)
                ->where('decision', ApprovalDecision::Approved->value)
                ->exists();

            if (! $approved) {
                return false;
            }
        }

        return true;
    }

    public function document(KimApplication $application): ?BeritaAcaraDocument
    {
        return $application->beritaAcara()
            ->with('signatures')
            ->first();
    }

    /**
     * Bekukan BA bila syarat terpenuhi. Idempoten.
     */
    public function finalizeIfReady(KimApplication $application): ?BeritaAcaraDocument
    {
        if (! $this->isEligible($application) || ! $this->isReadyForSignatures($application)) {
            return null;
        }

        if ($existing = $this->document($application)) {
            return $existing;
        }

        return DB::transaction(function () use ($application) {
            // Cegah pembekuan ganda saat dua request bersamaan.
            $application = KimApplication::lockForUpdate()->findOrFail($application->id);

            if ($existing = $this->document($application)) {
                return $existing;
            }

            $document = BeritaAcaraDocument::create([
                'kim_application_id' => $application->id,
                'number' => $application->application_number,
                'pdf_path' => '',
                'pdf_hash' => '',
                'legal_reference' => app(SettingsService::class)->legalReference(),
                'generated_at' => now(),
                'generated_by' => auth()->id(),
            ]);

            $this->signatureService->createFor($document, $application);

            $pdf = $this->reportService->render(
                $application,
                $this->signatureBlocks($document->fresh('signatures')),
                $document,
            );
            $binary = $pdf->output();

            $path = 'berita-acara/'.SafeFilename::make($application->application_number.'.pdf', 'berita-acara');
            Storage::disk($this->disk())->put($path, $binary);

            $document->update([
                'pdf_path' => $path,
                'pdf_hash' => hash('sha256', $binary),
            ]);

            app(ActivityLogger::class)->log(
                'berita_acara.finalized',
                $document,
                'Berita Acara '.$document->number.' dibekukan beserta tanda tangan elektronik.',
                ['number' => $document->number, 'pdf_hash' => $document->pdf_hash],
            );

            return $document->fresh('signatures');
        });
    }

    /**
     * Susun data blok tanda tangan untuk dirender pada PDF final.
     *
     * @return array<int, array<string, mixed>>
     */
    public function signatureBlocks(BeritaAcaraDocument $document): array
    {
        // Dokumen lama (sebelum fitur tanda tangan) dilengkapi saat dirender.
        $this->signatureService->ensureFor($document, $document->application);
        $document->load('signatures');

        return $this->signatureService->blocks(
            $document->signatures,
            Department::approvalSteps(),
            app(SettingsService::class)->baQrEnabled(),
        );
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

    /**
     * Unduh Berita Acara.
     *
     * Bila approval lengkap, berkas final yang dibekukan dilayani. Sebelum itu,
     * dokumen dinamis tanpa blok tanda tangan.
     */
    public function download(KimApplication $application)
    {
        $document = $this->finalizeIfReady($application);

        if ($document !== null) {
            return $this->storedResponse($document, $this->filename($application), inline: false);
        }

        return $this->preview($application)->download($this->filename($application));
    }

    /**
     * Tampilkan Berita Acara di browser (inline).
     */
    public function stream(KimApplication $application)
    {
        $document = $this->finalizeIfReady($application);

        if ($document !== null) {
            return $this->storedResponse($document, $this->filename($application), inline: true);
        }

        return $this->preview($application)->stream($this->filename($application));
    }

    /**
     * PDF pratinjau tanpa blok tanda tangan (sebelum approval lengkap).
     */
    public function preview(KimApplication $application)
    {
        return $this->reportService->render($application);
    }

    /**
     * Layani berkas PDF final yang sudah dibekukan.
     */
    private function storedResponse(BeritaAcaraDocument $document, string $filename, bool $inline)
    {
        $storage = Storage::disk($this->disk());

        if (! $storage->exists($document->pdf_path)) {
            // Berkas hilang: bangun ulang isi final dari tanda tangan tersimpan.
            $document->load('signatures');

            $binary = $this->reportService
                ->render($document->application, $this->signatureBlocks($document), $document)
                ->output();

            $storage->put($document->pdf_path, $binary);
            $document->update(['pdf_hash' => hash('sha256', $binary)]);
        }

        $disposition = ($inline ? 'inline' : 'attachment').'; filename="'.$filename.'"';

        return response($storage->get($document->pdf_path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition,
        ]);
    }
}
