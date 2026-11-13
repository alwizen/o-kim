<?php

namespace App\Services\Document;

use App\Enums\DocumentVerificationStatus;
use App\Models\DocumentType;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Notifications\VehicleDocumentUploaded;
use App\Services\Audit\ActivityLogger;
use App\Support\AppNotifier;
use App\Support\ImageCompressor;
use App\Support\SafeFilename;
use App\Support\SecureFileResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class VehicleDocumentService
{
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
    ];

    private const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

    private const MAX_SIZE_KILOBYTES = 5120;

    public function __construct(
        private readonly ImageCompressor $compressor = new ImageCompressor,
    ) {}

    private function disk(): string
    {
        return config('media.disk');
    }

    public function upload(Vehicle $vehicle, int $documentTypeId, UploadedFile $file, array $metadata = []): VehicleDocument
    {
        $this->validateFile($file);

        $documentType = DocumentType::query()
            ->whereKey($documentTypeId)
            ->where('is_active', true)
            ->first();

        if ($documentType === null) {
            throw ValidationException::withMessages([
                'document_type_id' => 'Jenis dokumen tidak tersedia atau sudah nonaktif.',
            ]);
        }

        if ($documentType->has_expiry_date && blank($metadata['expires_at'] ?? null)) {
            throw ValidationException::withMessages([
                'expires_at' => 'Tanggal kedaluwarsa wajib diisi untuk jenis dokumen ini.',
            ]);
        }

        return DB::transaction(function () use ($vehicle, $documentType, $documentTypeId, $file, $metadata) {
            $current = $vehicle->documents()
                ->where('document_type_id', $documentTypeId)
                ->whereNull('replaced_by_id')
                ->latest('id')
                ->first();

            // Kompres gambar agar hemat storage (PDF diteruskan apa adanya).
            [$file, $mimeType, $fileSize] = $this->compressor->compress($file);

            $path = $file->store(
                'vehicle-documents/'.$vehicle->storageFolder(),
                ['disk' => $this->disk()]
            );

            $document = VehicleDocument::create([
                'vehicle_id' => $vehicle->id,
                'document_type_id' => $documentTypeId,
                'document_number' => $metadata['document_number'] ?? null,
                'file_path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'issued_at' => $metadata['issued_at'] ?? null,
                'expires_at' => $metadata['expires_at'] ?? null,
                'verification_status' => DocumentVerificationStatus::Pending,
            ]);

            if ($current) {
                $current->update(['replaced_by_id' => $document->id]);
            }

            $documentId = $document->id;

            DB::afterCommit(fn () => $this->notifyStaffOfUpload($documentId));

            app(ActivityLogger::class)->log(
                'document.uploaded',
                $document,
                'Dokumen '.$documentType->name.' untuk '.$vehicle->registration_number.' diunggah.',
                [
                    'document_type_id' => $documentTypeId,
                    'document_number' => $metadata['document_number'] ?? null,
                    'replaced_document_id' => $current?->id,
                ],
            );

            return $document;
        });
    }

    /**
     * Beri tahu petugas verifikasi dokumen bahwa ada dokumen baru menunggu.
     */
    private function notifyStaffOfUpload(int $documentId): void
    {
        $document = VehicleDocument::with(['vehicle.transporter', 'documentType'])->find($documentId);

        if ($document === null) {
            return;
        }

        AppNotifier::notifyStaffWithPermission(
            'verify_documents',
            new VehicleDocumentUploaded(
                $document->vehicle?->transporter?->company_name ?? '-',
                $document->vehicle?->registration_number ?? '-',
                $document->documentType?->name ?? 'Dokumen',
            ),
        );
    }

    /**
     * Perbarui metadata dokumen kendaraan.
     *
     * Dokumen yang sudah diverifikasi bersifat final: metadata tidak boleh
     * diubah agar dasar penerbitan KIM tetap dapat diaudit. Untuk memperbarui,
     * unggah dokumen baru (versi lama otomatis ditandai tergantikan).
     *
     * @param  array<string, mixed>  $metadata
     *
     * @throws ValidationException bila dokumen sudah terverifikasi
     */
    public function update(VehicleDocument $document, array $metadata): VehicleDocument
    {
        $this->ensureMutable($document);

        $documentType = DocumentType::find($document->document_type_id);

        if ($documentType?->has_expiry_date && blank($metadata['expires_at'] ?? $document->expires_at)) {
            throw ValidationException::withMessages([
                'expires_at' => 'Tanggal kedaluwarsa wajib diisi untuk jenis dokumen ini.',
            ]);
        }

        return DB::transaction(function () use ($document, $metadata) {
            $document->update([
                'document_type_id' => $metadata['document_type_id'] ?? $document->document_type_id,
                'document_number' => $metadata['document_number'] ?? null,
                'issued_at' => $metadata['issued_at'] ?? null,
                'expires_at' => $metadata['expires_at'] ?? null,
            ]);

            app(ActivityLogger::class)->log(
                'document.updated',
                $document->vehicle,
                'Dokumen '.($document->documentType?->name ?? 'kendaraan').' untuk '
                    .($document->vehicle?->registration_number ?? '-').' diperbarui.',
                [
                    'document_id' => $document->id,
                    'document_type_id' => $document->document_type_id,
                    'document_number' => $document->document_number,
                ],
            );

            return $document->refresh();
        });
    }

    /**
     * Hapus dokumen kendaraan.
     *
     * Dokumen yang sudah diverifikasi bersifat final: tidak boleh dihapus agar
     * dasar penerbitan KIM tetap dapat diaudit. Untuk memperbarui, unggah
     * dokumen baru (versi lama otomatis ditandai tergantikan).
     */
    public function delete(VehicleDocument $document): void
    {
        $this->ensureMutable($document);

        DB::transaction(function () use ($document) {
            Storage::disk($this->disk())->delete($document->file_path);

            app(ActivityLogger::class)->log(
                'document.deleted',
                $document->vehicle,
                'Dokumen '.($document->documentType?->name ?? 'kendaraan').' untuk '
                    .($document->vehicle?->registration_number ?? '-').' dihapus.',
                [
                    'document_id' => $document->id,
                    'document_type_id' => $document->document_type_id,
                    'document_number' => $document->document_number,
                ],
            );

            $document->delete();
        });
    }

    /**
     * @throws ValidationException bila dokumen sudah terverifikasi
     */
    private function ensureMutable(VehicleDocument $document): void
    {
        if ($document->verification_status === DocumentVerificationStatus::Verified) {
            throw ValidationException::withMessages([
                'document' => 'Dokumen yang sudah terverifikasi tidak dapat diubah atau dihapus. Unggah dokumen baru untuk memperbarui.',
            ]);
        }
    }

    public function download(VehicleDocument $document)
    {
        $this->ensureFileExists($document);

        return Storage::disk($this->disk())->download(
            $document->file_path,
            SafeFilename::make($document->original_filename),
            SecureFileResponse::DOWNLOAD_HEADERS
        );
    }

    /**
     * Tampilkan berkas secara inline (pratinjau di tab baru).
     */
    public function preview(VehicleDocument $document)
    {
        $this->ensureFileExists($document);

        return Storage::disk($this->disk())->response(
            $document->file_path,
            SafeFilename::make($document->original_filename),
            [
                'Content-Type' => $document->mime_type,
                ...SecureFileResponse::PREVIEW_HEADERS,
            ],
            'inline'
        );
    }

    private function ensureFileExists(VehicleDocument $document): void
    {
        abort_unless(
            Storage::disk($this->disk())->exists($document->file_path),
            404,
            'Berkas tidak ditemukan.'
        );
    }

    private function validateFile(UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)
            || ! in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
            throw ValidationException::withMessages([
                'file' => 'Format berkas tidak diizinkan. Gunakan PDF, JPG, atau PNG.',
            ]);
        }

        if ($file->getSize() > self::MAX_SIZE_KILOBYTES * 1024) {
            throw ValidationException::withMessages([
                'file' => 'Ukuran berkas melebihi batas maksimum 5 MB.',
            ]);
        }
    }
}
