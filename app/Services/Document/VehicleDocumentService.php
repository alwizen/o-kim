<?php

namespace App\Services\Document;

use App\Enums\DocumentVerificationStatus;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Support\SafeFilename;
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

    private const MAX_SIZE_KILOBYTES = 5120;

    private const DISK = 'local';

    public function upload(Vehicle $vehicle, int $documentTypeId, UploadedFile $file, array $metadata = []): VehicleDocument
    {
        $this->validateFile($file);

        return DB::transaction(function () use ($vehicle, $documentTypeId, $file, $metadata) {
            $current = $vehicle->documents()
                ->where('document_type_id', $documentTypeId)
                ->whereNull('replaced_by_id')
                ->latest('id')
                ->first();

            $path = $file->store(
                "vehicle-documents/{$vehicle->id}",
                ['disk' => self::DISK]
            );

            $document = VehicleDocument::create([
                'vehicle_id' => $vehicle->id,
                'document_type_id' => $documentTypeId,
                'document_number' => $metadata['document_number'] ?? null,
                'file_path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'issued_at' => $metadata['issued_at'] ?? null,
                'expires_at' => $metadata['expires_at'] ?? null,
                'verification_status' => DocumentVerificationStatus::Pending,
            ]);

            if ($current) {
                $current->update(['replaced_by_id' => $document->id]);
            }

            return $document;
        });
    }

    public function delete(VehicleDocument $document): void
    {
        DB::transaction(function () use ($document) {
            Storage::disk(self::DISK)->delete($document->file_path);
            $document->delete();
        });
    }

    public function download(VehicleDocument $document)
    {
        abort_unless(
            Storage::disk(self::DISK)->exists($document->file_path),
            404,
            'Berkas tidak ditemukan.'
        );

        return Storage::disk(self::DISK)->download(
            $document->file_path,
            SafeFilename::make($document->original_filename)
        );
    }

    private function validateFile(UploadedFile $file): void
    {
        if (! in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
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
