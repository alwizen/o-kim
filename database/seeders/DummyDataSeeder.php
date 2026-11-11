<?php

namespace Database\Seeders;

use App\Actions\Document\VerifyVehicleDocument;
use App\Enums\AccountStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\ApprovalDecision;
use App\Enums\Department;
use App\Enums\DocumentScanType;
use App\Enums\InspectionResultValue;
use App\Enums\TransporterStatus;
use App\Models\DocumentScanLog;
use App\Models\DocumentType;
use App\Models\Finding;
use App\Models\InspectionItem;
use App\Models\KimApplication;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Application\KimApplicationService;
use App\Services\Approval\ApprovalService;
use App\Services\Document\DocumentSignatureService;
use App\Services\Document\VehicleDocumentService;
use App\Services\Finding\BeritaAcaraService;
use App\Services\Finding\FindingService;
use App\Services\Inspection\InspectionAttachmentService;
use App\Services\Inspection\InspectionService;
use App\Services\Kim\KimIssuanceService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Data dummy untuk pengembangan & demonstrasi.
 *
 * SENGAJA TIDAK didaftarkan di DatabaseSeeder, sehingga `migrate:fresh --seed`
 * TIDAK ikut membuat data dummy. Jalankan secara eksplisit:
 *
 *     php artisan db:seed --class=DummyDataSeeder
 *
 * Seeder ini idempoten (memakai updateOrCreate), aman dijalankan berulang.
 */
class DummyDataSeeder extends Seeder
{
    /** Password seragam untuk seluruh akun dummy. */
    private const PASSWORD = 'password';

    /** @var array<string, User> */
    private array $staff = [];

    /** @var array<string, Transporter> */
    private array $transporters = [];

    /** @var array<string, User> */
    private array $owners = [];

    /** @var array<string, Vehicle> */
    private array $vehicles = [];

    public function run(): void
    {
        // 1. Data master (wajib ada sebelum data transaksional).
        $this->call([
            RolePermissionSeeder::class,
            SettingSeeder::class,
            NotificationSettingSeeder::class,
            DocumentTypeSeeder::class,
            InspectionChecklistSeeder::class,
        ]);

        $this->seedStaff();
        $this->seedTransporters();
        $this->seedVehiclesAndDocuments();
        $this->seedApplications();

        // Contoh log verifikasi QR (BA & KIM) dibuat terpisah agar tetap terisi
        // walau pengajuan sudah ada dari seeding sebelumnya.
        $this->seedScanLogs();

        $this->report();
    }

    // ------------------------------------------------------------------
    // 1. Petugas internal
    // ------------------------------------------------------------------

    private function seedStaff(): void
    {
        $staff = [
            'super_admin' => ['Super Admin', 'superadmin@okim.test', 'Administrator Sistem'],
            'admin_verifikasi' => ['Admin Verifikasi', 'adminverifikasi@okim.test', 'Staf Verifikasi Dokumen'],
            'hsse' => ['Rohmad HSSE', 'hsse@okim.test', 'TAD HSSE'],
            'distribusi' => ['Munandar Distribusi', 'distribusi@okim.test', 'TAD Distribusi'],
            'qq' => ['Monik QQ', 'qq@okim.test', 'TAD QQ'],
            'pengawas_hsse' => ['Alika Pengawas HSSE', 'pengawashsse@okim.test', 'Pengawas HSSE'],
            'pengawas_distribusi' => ['Dafa Pengawas Distribusi', 'pengawasdistribusi@okim.test', 'Pengawas Distribusi'],
            'pengawas_qq' => ['Uli Pengawas QQ', 'pengawasqq@okim.test', 'Pengawas QQ'],
            'rsd' => ['Rahmat Pengawas RSD', 'rsd@okim.test', 'Pengawas RSD'],
            'ftm' => ['Saiful Hamzah', 'ftm@okim.test', 'Fuel Terminal Manager'],
        ];

        foreach ($staff as $role => [$name, $email, $jabatan]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'jabatan' => $jabatan,
                    'password' => Hash::make(self::PASSWORD),
                    'account_status' => AccountStatus::Approved,
                ],
            );

            // Role `rsd` sengaja tidak di-seed; bila belum dibuat manual di UI,
            // beri permission approval-nya langsung agar demo tetap berjalan.
            if ($role === 'rsd' && ! Role::where('name', 'rsd')->exists()) {
                $user->syncRoles([]);
                $user->syncPermissions(['access_admin_panel', 'view_applications', 'approve_rsd']);
            } else {
                $user->syncRoles([$role]);
            }

            $this->staff[$role] = $user;
        }
    }

    // ------------------------------------------------------------------
    // 2. Transportir + akun pemiliknya
    // ------------------------------------------------------------------

    private function seedTransporters(): void
    {
        $data = [
            'approved' => [
                'PT Trans Jaya Mandiri', 'transjaya@okim.test', 'Budi Trans Jaya',
                'Jl. Raya Kaligawe No. 12, Semarang', '024-6712345', 'NIB-2024000111',
                TransporterStatus::Approved, null,
            ],
            'pending' => [
                'CV Mitra Tanki Sejahtera', 'mitratanki@okim.test', 'Siti Mitra Tanki',
                'Jl. Industri Raya No. 8, Tegal', '0283-445566', 'NIB-2024000222',
                TransporterStatus::Pending, null,
            ],
            'rejected' => [
                'PT Sinar Abadi Logistik', 'sinarabadi@okim.test', 'Andi Sinar Abadi',
                'Jl. Pantura Km. 7, Pekalongan', '0285-778899', 'NIB-2024000333',
                TransporterStatus::Rejected, 'Dokumen legalitas perusahaan tidak lengkap.',
            ],
        ];

        foreach ($data as $key => [$company, $email, $name, $address, $phone, $nib, $status, $reason]) {
            $transporter = Transporter::updateOrCreate(
                ['legal_identifier' => $nib],
                [
                    'company_name' => $company,
                    'company_address' => $address,
                    'phone' => $phone,
                    'status' => $status,
                    'rejection_reason' => $reason,
                    'profile_submitted_at' => now()->subDays(20),
                ],
            );

            $owner = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'jabatan' => 'Penanggung Jawab Transportir',
                    'password' => Hash::make(self::PASSWORD),
                    'account_status' => $status === TransporterStatus::Approved
                        ? AccountStatus::Approved
                        : AccountStatus::Pending,
                    'rejection_reason' => $reason,
                ],
            );

            $owner->syncRoles(['transportir']);

            if ($owner->transporters()->where('transporters.id', $transporter->id)->doesntExist()) {
                $owner->transporters()->attach($transporter);
            }

            $this->transporters[$key] = $transporter;
            $this->owners[$key] = $owner;
        }
    }

    // ------------------------------------------------------------------
    // 3. Kendaraan + dokumen
    // ------------------------------------------------------------------

    private function seedVehiclesAndDocuments(): void
    {
        // transportir key => daftar [nopol, merek, model, tahun, kapasitas]
        $vehicles = [
            'approved' => [
                ['B 9456 SFV', 'Hino', 'Ranger FM 260', 2021, 24000],
                ['B 9123 TGL', 'Mitsubishi', 'Fuso Fighter FN', 2020, 16000],
            ],
            'pending' => [
                ['G 8811 AB', 'Isuzu', 'Giga FVR', 2019, 16000],
            ],
            'rejected' => [
                ['D 7722 CD', 'Hino', 'Dutro 130 HD', 2018, 8000],
            ],
        ];

        $index = 0;

        foreach ($vehicles as $transporterKey => $list) {
            foreach ($list as $i => [$nopol, $brand, $model, $year, $capacity]) {
                $vehicle = Vehicle::updateOrCreate(
                    ['registration_number' => $nopol],
                    [
                        'transporter_id' => $this->transporters[$transporterKey]->id,
                        'brand' => $brand,
                        'model' => $model,
                        'manufacture_year' => $year,
                        'tank_capacity' => $capacity,
                        'chassis_number' => strtoupper('MH'.$index.'ABC'.$year.'XYZ'),
                        'engine_number' => strtoupper('ENG'.$index.$year.'9999'),
                        'transport_type' => 'BBM',
                        'tank_material' => 'Baja Karbon',
                    ],
                );

                $this->vehicles[$nopol] = $vehicle;

                // Variasi status dokumen: verified, pending, hampir kedaluwarsa.
                $this->seedDocuments($vehicle, $transporterKey, $index);
                $index++;
            }
        }
    }

    private function seedDocuments(Vehicle $vehicle, string $transporterKey, int $index): void
    {
        $documentService = app(VehicleDocumentService::class);
        $verifier = $this->staff['admin_verifikasi'];

        // Kendaraan transportir Approved: STNK/KEUR terverifikasi dengan masa
        // berlaku berbeda (satu kendaraan sengaja mendekati kedaluwarsa).
        $expiryMap = [
            ['STNK', now()->addMonths(10)],
            ['KEUR', $index === 0 ? now()->addDays(25) : now()->addMonths(8)],
            ['TERA', now()->addMonths(6)],
        ];

        $shouldVerify = $transporterKey === 'approved';

        foreach ($expiryMap as [$code, $expiry]) {
            if ($vehicle->documents()->whereHas('documentType', fn ($q) => $q->where('code', $code))->exists()) {
                continue;
            }

            $typeId = DocumentType::where('code', $code)->value('id');

            if ($typeId === null) {
                continue;
            }

            $document = $documentService->upload(
                $vehicle,
                $typeId,
                $this->fakePdf("{$code}-{$vehicle->registration_number}.pdf"),
                [
                    'document_number' => strtoupper($code).'-'.$index.str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT),
                    'expires_at' => $expiry->toDateString(),
                ],
            );

            if ($shouldVerify) {
                app(VerifyVehicleDocument::class)->execute($document, $verifier);
            }
        }
    }

    // ------------------------------------------------------------------
    // 4. Pengajuan (berbagai tahap siklus)
    // ------------------------------------------------------------------

    private function seedApplications(): void
    {
        // 4a. Pengajuan lengkap sampai KIM terbit.
        if (! $this->hasApplicationFor('B 9456 SFV')) {
            $this->buildIssuedApplication($this->vehicles['B 9456 SFV']);
        }

        // 4b. Pengajuan dengan temuan terverifikasi → siap cetak Berita Acara.
        if (! $this->hasApplicationFor('B 9123 TGL')) {
            $this->buildVerifiedFindingApplication($this->vehicles['B 9123 TGL']);
        }

        // 4c. Pengajuan dengan temuan terbuka (Perlu Perbaikan).
        if (! $this->hasApplicationFor('G 8811 AB')) {
            $this->buildOpenFindingApplication($this->vehicles['G 8811 AB']);
        }
    }

    private function hasApplicationFor(string $nopol): bool
    {
        return $this->vehicles[$nopol]->kimApplications()->exists();
    }

    /**
     * Pengajuan lengkap: checklist penuh → approval 3 fungsi → approval RSD → FTM → KIM.
     */
    private function buildIssuedApplication(Vehicle $vehicle): void
    {
        $owner = $this->ownerOf($vehicle);
        $application = $this->startApplication($vehicle, $owner);

        $this->completeChecklist($application);
        $this->approveAll($application);
        app(KimIssuanceService::class)->issue($application->fresh());
    }

    /**
     * Pengajuan dengan satu temuan yang sudah ditindaklanjuti & diverifikasi.
     */
    private function buildVerifiedFindingApplication(Vehicle $vehicle): void
    {
        $owner = $this->ownerOf($vehicle);
        $application = $this->startApplication($vehicle, $owner);

        $finding = $this->completeChecklist($application, createFinding: true);

        $action = app(FindingService::class)->submitAction(
            $finding,
            $owner,
            'Perbaikan sudah dilakukan, selang diganti baru.',
        );

        app(FindingService::class)->verifyAction(
            $finding,
            $action,
            $this->staff['hsse'],
            true,
            'Bukti sesuai, perbaikan diterima.',
        );

        $this->approveAll($application->fresh());

        // Bekukan Berita Acara bertanda tangan QR (5 tanda tangan).
        app(BeritaAcaraService::class)->finalizeIfReady($application->fresh());
    }

    /**
     * Contoh log pemindaian QR untuk seluruh BA dan kartu KIM yang ada.
     */
    private function seedScanLogs(): void
    {
        $signatureService = app(DocumentSignatureService::class);

        foreach (KimApplication::query()->with('beritaAcara.signatures', 'kimCard.signatures')->get() as $application) {
            $document = $application->beritaAcara;

            if ($document !== null) {
                // Backfill tanda tangan untuk dokumen lama.
                $signatureService->ensureFor($document, $application);
                $document->load('signatures');

                foreach ($document->signatures->take(3) as $offset => $signature) {
                    $this->createScanLogIfMissing([
                        'document_type' => DocumentScanType::BeritaAcara,
                        'document_signature_id' => $signature->id,
                        'ip_address' => '10.10.'.(10 + $offset).'.'.(20 + $offset),
                        'user_agent' => 'Mozilla/5.0 (Demo Scanner)',
                        'scanned_at' => now()->subHours($offset + 1),
                    ]);
                }
            }

            if ($application->kimCard !== null) {
                // Backfill tanda tangan untuk kartu lama.
                $signatureService->ensureFor($application->kimCard, $application);
                $application->kimCard->load('signatures');

                foreach ($application->kimCard->signatures->take(2) as $offset => $signature) {
                    $this->createScanLogIfMissing([
                        'document_type' => DocumentScanType::Kim,
                        'document_signature_id' => $signature->id,
                        'ip_address' => '10.10.'.(30 + $offset).'.'.(40 + $offset),
                        'user_agent' => 'Mozilla/5.0 (Demo Scanner)',
                        'scanned_at' => now()->subHours(2 + $offset),
                    ]);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createScanLogIfMissing(array $attributes): void
    {
        DocumentScanLog::firstOrCreate(
            [
                'document_type' => $attributes['document_type'],
                'document_signature_id' => $attributes['document_signature_id'] ?? null,
                'kim_card_id' => $attributes['kim_card_id'] ?? null,
                'scanned_at' => $attributes['scanned_at'],
            ],
            [
                'ip_address' => $attributes['ip_address'],
                'user_agent' => $attributes['user_agent'],
            ],
        );
    }

    /**
     * Pengajuan dengan temuan terbuka, menunggu tindak lanjut transportir.
     */
    private function buildOpenFindingApplication(Vehicle $vehicle): void
    {
        $owner = $this->ownerOf($vehicle);
        $application = $this->startApplication($vehicle, $owner);

        $this->completeChecklist($application, createFinding: true);

        // Tetapkan ke Perlu Perbaikan agar sesuai kondisi nyata.
        if ($application->fresh()->status === ApplicationStatus::Inspection) {
            $application->fresh()->update(['status' => ApplicationStatus::RevisionRequired]);
        }
    }

    private function ownerOf(Vehicle $vehicle): User
    {
        foreach ($this->transporters as $key => $transporter) {
            if ($transporter->id === $vehicle->transporter_id) {
                return $this->owners[$key];
            }
        }

        throw new \RuntimeException('Pemilik transportir tidak ditemukan untuk kendaraan '.$vehicle->registration_number);
    }

    private function startApplication(Vehicle $vehicle, User $owner): KimApplication
    {
        $service = app(KimApplicationService::class);

        $application = $service->createDraft($vehicle, ApplicationType::New);
        $service->submit($application, $owner);

        $application->update([
            'status' => ApplicationStatus::Inspection,
            'inspection_scheduled_at' => now()->subDay(),
            'inspection_scheduled_by' => $this->staff['admin_verifikasi']->id,
        ]);

        return $application->fresh();
    }

    /**
     * Isi seluruh butir checklist setiap fungsi sebagai Sesuai.
     * Bila $createFinding, satu butir HSSE dijadikan Temuan.
     */
    private function completeChecklist(KimApplication $application, bool $createFinding = false): ?Finding
    {
        $inspection = app(InspectionService::class);
        $finding = null;

        foreach (Department::inspectingDepartments() as $department) {
            $officer = $this->staff[$department->value];

            $items = InspectionItem::query()
                ->whereHas('checklist', fn ($q) => $q->where('department', $department->value)->where('is_active', true))
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('item_code')
                ->get();

            foreach ($items as $index => $item) {
                $isTarget = $createFinding
                    && $finding === null
                    && $department === Department::Hsse
                    && $index === 0;

                $result = $inspection->recordResult(
                    $application->fresh(),
                    $item,
                    $isTarget ? InspectionResultValue::Finding : InspectionResultValue::Compliant,
                    $officer,
                    findingDescription: $isTarget ? 'Selang apar foam retak dan perlu diganti.' : null,
                );

                if ($isTarget) {
                    // Lampirkan foto bukti pemeriksaan (dipakai pada Berita Acara).
                    app(InspectionAttachmentService::class)->upload(
                        $result,
                        $officer,
                        [$this->fakeJpeg('temuan-awal.jpg')],
                    );

                    $finding = $application->fresh()->findings()->latest('id')->first();
                }
            }
        }

        return $finding;
    }

    /**
     * Setujui seluruh fungsi (termasuk RSD) lalu approval final FTM.
     */
    private function approveAll(KimApplication $application): void
    {
        $approval = app(ApprovalService::class);

        foreach (Department::inspectingDepartments() as $department) {
            $approver = $this->staff['pengawas_'.$department->value];

            $approval->decide(
                $application->fresh(),
                $department,
                ApprovalDecision::Approved,
                $approver,
                'Checklist lengkap, disetujui.',
            );
        }

        // Pengawas RSD (wakil FTM) menyetujui sebelum approval final.
        if (! $application->fresh()->hasActiveDispensation()) {
            $approval->decide(
                $application->fresh(),
                Department::Rsd,
                ApprovalDecision::Approved,
                $this->staff['rsd'],
                'Diverifikasi RSD, diteruskan ke FTM.',
            );
        }

        $approval->decide(
            $application->fresh(),
            Department::Ftm,
            ApprovalDecision::Approved,
            $this->staff['ftm'],
            'Disetujui untuk penerbitan KIM.',
        );
    }

    // ------------------------------------------------------------------
    // 5. Berkas dummy
    // ------------------------------------------------------------------

    private function fakePdf(string $name): UploadedFile
    {
        $content = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            ."2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\n"
            ."trailer<</Root 1 0 R>>\n%%EOF";

        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function fakeJpeg(string $name): UploadedFile
    {
        $image = imagecreatetruecolor(320, 240);
        imagefill($image, 0, 0, imagecolorallocate($image, 190, 60, 60));
        imagestring($image, 5, 20, 110, 'FOTO TEMUAN', imagecolorallocate($image, 255, 255, 255));

        ob_start();
        imagejpeg($image);
        $binary = (string) ob_get_clean();
        imagedestroy($image);

        return UploadedFile::fake()->createWithContent($name, $binary);
    }

    // ------------------------------------------------------------------
    // 6. Ringkasan
    // ------------------------------------------------------------------

    private function report(): void
    {
        $this->command->newLine();
        $this->command->info('=== Data Dummy O-KIM ===');
        $this->command->line('Password seluruh akun: '.self::PASSWORD);
        $this->command->newLine();

        $this->command->line('PENGGUNA INTERNAL (@okim.test):');
        foreach ($this->staff as $role => $user) {
            $this->command->line(sprintf('  %-22s %s', $role, $user->email));
        }

        $this->command->newLine();
        $this->command->line('TRANSPORTIR:');
        foreach ($this->transporters as $key => $transporter) {
            $this->command->line(sprintf(
                '  %-9s %-30s %s',
                $key,
                $transporter->company_name,
                $this->owners[$key]->email,
            ));
        }

        $this->command->newLine();
        $this->command->line(sprintf(
            'Total: %d kendaraan, %d pengajuan, %d temuan, %d KIM terbit.',
            Vehicle::count(),
            KimApplication::count(),
            Finding::count(),
            KimApplication::where('status', ApplicationStatus::Issued->value)->count(),
        ));
        $this->command->newLine();
    }
}
