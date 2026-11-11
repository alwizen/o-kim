<?php

namespace App\Enums;

use App\Notifications\ApplicationApproved;
use App\Notifications\ApplicationApprovedByDepartment;
use App\Notifications\ApplicationPendingDepartmentApproval;
use App\Notifications\ApplicationPendingFinalApproval;
use App\Notifications\ApplicationRejected;
use App\Notifications\ExpiryReminder;
use App\Notifications\ExpiryReminderStaff;
use App\Notifications\FindingActionSubmitted;
use App\Notifications\FindingCreated;
use App\Notifications\FindingDispensationExpired;
use App\Notifications\FindingDispensationExpiredStaff;
use App\Notifications\FindingDispensationGranted;
use App\Notifications\FindingDispensationGrantedStaff;
use App\Notifications\InspectionScheduled;
use App\Notifications\InspectionScheduledForStaff;
use App\Notifications\InternalAccountApproved;
use App\Notifications\InternalAccountRejected;
use App\Notifications\InternalRegistrationSubmitted;
use App\Notifications\KimApplicationSubmitted;
use App\Notifications\KimCardIssued;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\TransporterApproved;
use App\Notifications\TransporterRegistered;
use App\Notifications\TransporterRegistrationSubmitted;
use App\Notifications\TransporterRejected;
use App\Notifications\VehicleCreated;
use App\Notifications\VehicleDocumentRejected;
use App\Notifications\VehicleDocumentUploaded;
use App\Notifications\VehicleDocumentVerified;
use Illuminate\Notifications\Notification;

/**
 * Katalog notifikasi email yang dapat diatur super admin
 * (menu User Manajemen → Notifikasi Email).
 *
 * Setiap event menentukan kelas notifikasi, grup tampilan, role penerima
 * bawaan, dan apakah boleh dimatikan. Email difilter berdasarkan role;
 * notifikasi lonceng panel tidak terpengaruh.
 */
enum NotificationEvent: string
{
    // ---------- Transportir ----------
    case TransporterRegistrationReceived = 'transporter.registration_received';
    case TransporterAccountApproved = 'transporter.account_approved';
    case TransporterAccountRejected = 'transporter.account_rejected';
    case TransporterDocumentVerified = 'transporter.document_verified';
    case TransporterDocumentRejected = 'transporter.document_rejected';
    case TransporterInspectionScheduled = 'transporter.inspection_scheduled';
    case TransporterApprovalProgress = 'transporter.approval_progress';
    case TransporterApplicationApproved = 'transporter.application_approved';
    case TransporterApplicationRejected = 'transporter.application_rejected';
    case TransporterFindingCreated = 'transporter.finding_created';
    case TransporterFindingDispensationGranted = 'transporter.finding_dispensation_granted';
    case TransporterFindingDispensationExpired = 'transporter.finding_dispensation_expired';
    case TransporterKimIssued = 'transporter.kim_issued';
    case TransporterExpiryReminder = 'transporter.expiry_reminder';

    // ---------- Internal ----------
    case InternalTransporterRegistration = 'internal.transporter_registration';
    case InternalInternalRegistration = 'internal.internal_registration';
    case InternalVehicleCreated = 'internal.vehicle_created';
    case InternalDocumentUploaded = 'internal.document_uploaded';
    case InternalApplicationSubmitted = 'internal.application_submitted';
    case InternalInspectionScheduled = 'internal.inspection_scheduled';
    case InternalFindingActionSubmitted = 'internal.finding_action_submitted';
    case InternalFindingDispensationGranted = 'internal.finding_dispensation_granted';
    case InternalFindingDispensationExpired = 'internal.finding_dispensation_expired';
    case InternalDepartmentApprovalNeeded = 'internal.department_approval_needed';
    case InternalFinalApprovalNeeded = 'internal.final_approval_needed';
    case InternalExpiryReminder = 'internal.expiry_reminder';

    // ---------- Akun & keamanan (selalu aktif) ----------
    case InternalAccountApproved = 'internal.account_approved';
    case InternalAccountRejected = 'internal.account_rejected';
    case AccountPasswordReset = 'account.password_reset';

    public const GROUP_TRANSPORTER = 'Transportir';

    public const GROUP_INTERNAL = 'Internal';

    public const GROUP_ACCOUNT = 'Akun & Keamanan';

    public function label(): string
    {
        return match ($this) {
            self::TransporterRegistrationReceived => 'Pendaftaran Akun Diterima',
            self::TransporterAccountApproved => 'Akun Disetujui',
            self::TransporterAccountRejected => 'Akun Ditolak',
            self::TransporterDocumentVerified => 'Dokumen Diverifikasi',
            self::TransporterDocumentRejected => 'Dokumen Ditolak',
            self::TransporterInspectionScheduled => 'Jadwal Pemeriksaan',
            self::TransporterApprovalProgress => 'Progress Approval',
            self::TransporterApplicationApproved => 'Pengajuan Disetujui',
            self::TransporterApplicationRejected => 'Pengajuan Ditolak',
            self::TransporterFindingCreated => 'Temuan Checklist',
            self::TransporterFindingDispensationGranted => 'Temuan Didispensasi FTM',
            self::TransporterFindingDispensationExpired => 'KIM Sementara Kedaluwarsa',
            self::TransporterKimIssued => 'KIM Terbit',
            self::TransporterExpiryReminder => 'Pengingat Kedaluwarsa (Harian)',
            self::InternalTransporterRegistration => 'Pendaftaran Transportir Baru',
            self::InternalInternalRegistration => 'Pendaftaran User Internal',
            self::InternalVehicleCreated => 'Kendaraan Baru',
            self::InternalDocumentUploaded => 'Dokumen Kendaraan Diunggah',
            self::InternalApplicationSubmitted => 'Pengajuan KIM Baru',
            self::InternalInspectionScheduled => 'Jadwal / Reschedule Checklist',
            self::InternalFindingActionSubmitted => 'Transportir Kirim Tindak Lanjut',
            self::InternalFindingDispensationGranted => 'Dispensasi Temuan Diberikan',
            self::InternalFindingDispensationExpired => 'KIM Sementara Kedaluwarsa',
            self::InternalDepartmentApprovalNeeded => 'Membutuhkan Approval Fungsi',
            self::InternalFinalApprovalNeeded => 'Membutuhkan Approval Final',
            self::InternalExpiryReminder => 'Ringkasan Kedaluwarsa (Harian)',
            self::InternalAccountApproved => 'Akun Internal Disetujui',
            self::InternalAccountRejected => 'Akun Internal Ditolak',
            self::AccountPasswordReset => 'Reset Kata Sandi',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::TransporterRegistrationReceived => 'Dikirim saat transportir menyimpan profil perusahaan pertama kali.',
            self::TransporterAccountApproved => 'Dikirim saat admin menyetujui pendaftaran transportir.',
            self::TransporterAccountRejected => 'Dikirim saat admin menolak pendaftaran transportir.',
            self::TransporterDocumentVerified => 'Dikirim saat petugas memverifikasi dokumen kendaraan.',
            self::TransporterDocumentRejected => 'Dikirim saat petugas menolak dokumen kendaraan.',
            self::TransporterInspectionScheduled => 'Dikirim saat admin menetapkan atau mengubah jadwal pemeriksaan.',
            self::TransporterApprovalProgress => 'Dikirim setiap fungsi (HSSE/Distribusi/QQ) menyetujui pengajuan.',
            self::TransporterApplicationApproved => 'Dikirim saat Fuel Terminal Manager menyetujui final.',
            self::TransporterApplicationRejected => 'Dikirim saat pengajuan KIM ditolak.',
            self::TransporterFindingCreated => 'Dikirim saat petugas mencatat temuan pemeriksaan.',
            self::TransporterFindingDispensationGranted => 'Dikirim saat FTM memberi dispensasi atas temuan.',
            self::TransporterFindingDispensationExpired => 'Dikirim saat KIM sementara kedaluwarsa karena batas dispensasi terlampaui.',
            self::TransporterKimIssued => 'Dikirim saat KIM diterbitkan.',
            self::TransporterExpiryReminder => 'Email harian berisi dokumen dan KIM yang akan kedaluwarsa.',
            self::InternalTransporterRegistration => 'Dikirim saat ada pendaftaran transportir baru menunggu verifikasi.',
            self::InternalInternalRegistration => 'Dikirim saat ada pendaftaran pengguna internal baru.',
            self::InternalVehicleCreated => 'Dikirim saat transportir menambahkan kendaraan baru.',
            self::InternalDocumentUploaded => 'Dikirim saat transportir mengunggah dokumen kendaraan.',
            self::InternalApplicationSubmitted => 'Dikirim saat transportir mengirim pengajuan KIM.',
            self::InternalInspectionScheduled => 'Dikirim saat jadwal pemeriksaan ditetapkan atau diubah (mis. HSSE untuk checklist).',
            self::InternalFindingActionSubmitted => 'Dikirim saat transportir mengirim tindak lanjut temuan.',
            self::InternalFindingDispensationGranted => 'Dikirim ke FTM dan pengawas fungsi saat dispensasi diberikan.',
            self::InternalFindingDispensationExpired => 'Dikirim ke FTM dan pengawas fungsi saat KIM sementara kedaluwarsa.',
            self::InternalDepartmentApprovalNeeded => 'Dikirim saat checklist suatu fungsi selesai dan menunggu approval.',
            self::InternalFinalApprovalNeeded => 'Dikirim setelah RSD menyetujui dan menunggu approval final FTM.',
            self::InternalExpiryReminder => 'Ringkasan harian dokumen dan KIM yang akan kedaluwarsa untuk petugas.',
            self::InternalAccountApproved => 'Dikirim saat akun internal diaktifkan.',
            self::InternalAccountRejected => 'Dikirim saat akun internal ditolak.',
            self::AccountPasswordReset => 'Tautan reset kata sandi (hanya akun email + kata sandi).',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::TransporterRegistrationReceived,
            self::TransporterAccountApproved,
            self::TransporterAccountRejected,
            self::TransporterDocumentVerified,
            self::TransporterDocumentRejected,
            self::TransporterInspectionScheduled,
            self::TransporterApprovalProgress,
            self::TransporterApplicationApproved,
            self::TransporterApplicationRejected,
            self::TransporterFindingCreated,
            self::TransporterFindingDispensationGranted,
            self::TransporterFindingDispensationExpired,
            self::TransporterKimIssued,
            self::TransporterExpiryReminder => self::GROUP_TRANSPORTER,

            self::InternalAccountApproved,
            self::InternalAccountRejected,
            self::AccountPasswordReset => self::GROUP_ACCOUNT,

            default => self::GROUP_INTERNAL,
        };
    }

    /**
     * @return class-string<Notification>|null
     */
    public function notificationClass(): ?string
    {
        return match ($this) {
            self::TransporterRegistrationReceived => TransporterRegistered::class,
            self::TransporterAccountApproved => TransporterApproved::class,
            self::TransporterAccountRejected => TransporterRejected::class,
            self::TransporterDocumentVerified => VehicleDocumentVerified::class,
            self::TransporterDocumentRejected => VehicleDocumentRejected::class,
            self::TransporterInspectionScheduled => InspectionScheduled::class,
            self::TransporterApprovalProgress => ApplicationApprovedByDepartment::class,
            self::TransporterApplicationApproved => ApplicationApproved::class,
            self::TransporterApplicationRejected => ApplicationRejected::class,
            self::TransporterFindingCreated => FindingCreated::class,
            self::TransporterFindingDispensationGranted => FindingDispensationGranted::class,
            self::TransporterFindingDispensationExpired => FindingDispensationExpired::class,
            self::TransporterKimIssued => KimCardIssued::class,
            self::TransporterExpiryReminder => ExpiryReminder::class,
            self::InternalTransporterRegistration => TransporterRegistrationSubmitted::class,
            self::InternalInternalRegistration => InternalRegistrationSubmitted::class,
            self::InternalVehicleCreated => VehicleCreated::class,
            self::InternalDocumentUploaded => VehicleDocumentUploaded::class,
            self::InternalApplicationSubmitted => KimApplicationSubmitted::class,
            self::InternalInspectionScheduled => InspectionScheduledForStaff::class,
            self::InternalFindingActionSubmitted => FindingActionSubmitted::class,
            self::InternalFindingDispensationGranted => FindingDispensationGrantedStaff::class,
            self::InternalFindingDispensationExpired => FindingDispensationExpiredStaff::class,
            self::InternalDepartmentApprovalNeeded => ApplicationPendingDepartmentApproval::class,
            self::InternalFinalApprovalNeeded => ApplicationPendingFinalApproval::class,
            self::InternalExpiryReminder => ExpiryReminderStaff::class,
            self::InternalAccountApproved => InternalAccountApproved::class,
            self::InternalAccountRejected => InternalAccountRejected::class,
            self::AccountPasswordReset => ResetPasswordNotification::class,
        };
    }

    /**
     * Role penerima bawaan (dipakai saat seeder pertama kali mengisi data).
     *
     * @return array<int, string>
     */
    public function defaultRoles(): array
    {
        $pengawas = ['pengawas_hsse', 'pengawas_distribusi', 'pengawas_qq', 'rsd'];

        return match ($this) {
            self::TransporterRegistrationReceived,
            self::TransporterAccountApproved,
            self::TransporterAccountRejected,
            self::TransporterDocumentVerified,
            self::TransporterDocumentRejected,
            self::TransporterInspectionScheduled,
            self::TransporterApprovalProgress,
            self::TransporterApplicationApproved,
            self::TransporterApplicationRejected,
            self::TransporterFindingCreated,
            self::TransporterFindingDispensationGranted,
            self::TransporterFindingDispensationExpired,
            self::TransporterKimIssued,
            self::TransporterExpiryReminder => ['transportir'],

            self::InternalTransporterRegistration => ['admin_verifikasi', 'super_admin'],
            self::InternalInternalRegistration => ['super_admin'],
            self::InternalVehicleCreated => ['admin_verifikasi', 'ftm'],
            self::InternalDocumentUploaded => ['admin_verifikasi', 'ftm'],
            self::InternalApplicationSubmitted => array_merge(
                ['admin_verifikasi', 'hsse', 'distribusi', 'qq', 'ftm'],
                $pengawas,
                ['super_admin'],
            ),
            self::InternalInspectionScheduled => array_merge(['hsse', 'distribusi', 'qq', 'ftm'], $pengawas, ['super_admin']),
            self::InternalFindingActionSubmitted => array_merge(['hsse', 'distribusi', 'qq'], $pengawas, ['super_admin']),
            self::InternalFindingDispensationGranted,
            self::InternalFindingDispensationExpired => array_merge(['ftm'], $pengawas),
            self::InternalDepartmentApprovalNeeded => array_merge($pengawas, ['super_admin']),
            self::InternalFinalApprovalNeeded => ['ftm', 'super_admin'],
            self::InternalExpiryReminder => ['admin_verifikasi', 'ftm', 'super_admin'],
            self::InternalAccountApproved,
            self::InternalAccountRejected => ['super_admin'],
            self::AccountPasswordReset => [],
        };
    }

    /**
     * Event yang selalu mengirim email dan tidak dapat dimatikan.
     */
    public function locked(): bool
    {
        return in_array($this->value, [
            self::InternalAccountApproved->value,
            self::InternalAccountRejected->value,
            self::AccountPasswordReset->value,
        ], true);
    }

    /**
     * Cari event berdasarkan kelas notifikasi.
     */
    public static function tryFor(Notification $notification): ?self
    {
        return self::forClass($notification::class);
    }

    public static function forClass(string $class): ?self
    {
        foreach (self::cases() as $event) {
            if ($event->notificationClass() === $class) {
                return $event;
            }
        }

        return null;
    }

    /**
     * @return array<string, array<int, self>>
     */
    public static function grouped(): array
    {
        $grouped = [];

        foreach (self::cases() as $event) {
            $grouped[$event->group()][] = $event;
        }

        return $grouped;
    }

    /**
     * @return array<string, string>
     */
    public static function groupOptions(): array
    {
        return [
            self::GROUP_TRANSPORTER => 'Email untuk transportir',
            self::GROUP_INTERNAL => 'Email untuk admin / petugas internal',
            self::GROUP_ACCOUNT => 'Akun & keamanan (selalu aktif)',
        ];
    }

    public static function groupDescription(string $group): string
    {
        return match ($group) {
            self::GROUP_TRANSPORTER => 'Notifikasi yang dikirim ke PIC perusahaan transportir terkait.',
            self::GROUP_INTERNAL => 'Notifikasi pekerjaan internal — tentukan role yang perlu menerima email.',
            self::GROUP_ACCOUNT => 'Notifikasi keamanan akun; selalu dikirim dan tidak dapat dimatikan.',
            default => '',
        };
    }
}
