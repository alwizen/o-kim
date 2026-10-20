<?php

namespace App\Services\Inspection;

use App\Models\InspectionResult;
use App\Models\InspectionResultAttachment;
use App\Models\User;
use App\Support\SafeFilename;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class InspectionAttachmentService
{
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
    ];

    private const MAX_SIZE_KILOBYTES = 5120;

    private const MAX_FILES_PER_UPLOAD = 10;

    private const DISK = 'local';

    /**
     * Unggah satu atau beberapa berkas bukti pemeriksaan sekaligus.
     *
     * @param  array<UploadedFile>  $files
     * @return array<InspectionResultAttachment>
     */
    public function upload(InspectionResult $result, User $uploader, array $files): array
    {
        $files = array_values(array_filter($files, fn ($file) => $file instanceof UploadedFile));

        if ($files === []) {
            return [];
        }

        $this->ensureUploaderAuthorized($result, $uploader);

        if (count($files) > self::MAX_FILES_PER_UPLOAD) {
            throw ValidationException::withMessages([
                'attachments' => 'Maksimum '.self::MAX_FILES_PER_UPLOAD.' berkas per unggahan.',
            ]);
        }

        foreach ($files as $file) {
            $this->validateFile($file);
        }

        return DB::transaction(function () use ($result, $uploader, $files) {
            $created = [];

            foreach ($files as $file) {
                $path = $file->store(
                    "inspection-result-attachments/{$result->id}",
                    ['disk' => self::DISK]
                );

                $created[] = InspectionResultAttachment::create([
                    'inspection_result_id' => $result->id,
                    'uploaded_by' => $uploader->id,
                    'file_path' => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }

            return $created;
        });
    }

    /**
     * Hapus satu lampiran beserta berkas fisiknya.
     */
    public function delete(InspectionResultAttachment $attachment, User $user): void
    {
        $this->ensureUploaderAuthorized($attachment->result, $user);

        DB::transaction(function () use ($attachment) {
            Storage::disk(self::DISK)->delete($attachment->file_path);
            $attachment->delete();
        });
    }

    public function download(InspectionResultAttachment $attachment)
    {
        $this->ensureFileExists($attachment);

        return Storage::disk(self::DISK)->download(
            $attachment->file_path,
            SafeFilename::make($attachment->original_filename)
        );
    }

    /**
     * Tampilkan berkas bukti secara inline (pratinjau di tab baru).
     */
    public function preview(InspectionResultAttachment $attachment)
    {
        $this->ensureFileExists($attachment);

        return Storage::disk(self::DISK)->response(
            $attachment->file_path,
            SafeFilename::make($attachment->original_filename),
            ['Content-Type' => $attachment->mime_type],
            'inline'
        );
    }

    /**
     * Hanya petugas pemeriksa fungsi terkait yang boleh menambah/menghapus lampiran.
     */
    private function ensureUploaderAuthorized(InspectionResult $result, User $user): void
    {
        $department = $result->department();

        if ($department === null || ! $user->can($department->inspectPermission())) {
            throw ValidationException::withMessages([
                'attachments' => 'Anda tidak berwenang mengunggah bukti untuk butir pemeriksaan ini.',
            ]);
        }
    }

    private function ensureFileExists(InspectionResultAttachment $attachment): void
    {
        abort_unless(
            Storage::disk(self::DISK)->exists($attachment->file_path),
            404,
            'Berkas tidak ditemukan.'
        );
    }

    private function validateFile(UploadedFile $file): void
    {
        if (! in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
            throw ValidationException::withMessages([
                'attachments' => 'Format berkas tidak diizinkan. Gunakan PDF, JPG, atau PNG.',
            ]);
        }

        if ($file->getSize() > self::MAX_SIZE_KILOBYTES * 1024) {
            throw ValidationException::withMessages([
                'attachments' => 'Ukuran berkas melebihi batas maksimum 5 MB.',
            ]);
        }
    }
}
