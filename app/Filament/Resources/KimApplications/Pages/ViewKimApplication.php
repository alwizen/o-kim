<?php

namespace App\Filament\Resources\KimApplications\Pages;

use App\Actions\Document\RejectVehicleDocument;
use App\Actions\Document\VerifyVehicleDocument;
use App\Enums\ApplicationStatus;
use App\Enums\ApprovalDecision;
use App\Enums\Department;
use App\Enums\DocumentVerificationStatus;
use App\Filament\Resources\KimApplications\KimApplicationResource;
use App\Models\VehicleDocument;
use App\Notifications\InspectionScheduled;
use App\Notifications\InspectionScheduledForStaff;
use App\Services\Application\KimApplicationService;
use App\Services\Approval\ApprovalService;
use App\Services\Audit\ActivityLogger;
use App\Services\Finding\BeritaAcaraService;
use App\Services\Inspection\InspectionAttachmentService;
use App\Services\Kim\KimIssuanceService;
use App\Support\AppNotifier;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ViewKimApplication extends ViewRecord
{
    protected static string $resource = KimApplicationResource::class;

    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->loadMissing([
            'findings.inspectionResult.item',
            'findings.creator',
            'findings.dispensator',
            'inspectionResults.attachments',
            'vehicle.documents.documentType',
            'vehicle.documents.vehicle.transporter',
            'approvals.approver',
            'kimCard',
            'scheduler',
            'transporter',
        ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi Pengajuan')
                ->columnSpanFull()
                ->columns(4)
                ->schema([
                    TextEntry::make('application_number')->label('No. Pengajuan')->weight(FontWeight::Bold),
                    TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => $state->label()),
                    TextEntry::make('application_type')->label('Jenis')->formatStateUsing(fn ($state) => $state->label()),
                    TextEntry::make('submitted_at')->label('Dikirim')->dateTime('d M Y H:i')->placeholder('—'),
                    TextEntry::make('inspection_scheduled_at')
                        ->label('Jadwal Pemeriksaan')
                        ->dateTime('d M Y H:i')
                        ->placeholder('Belum dijadwalkan')
                        ->badge()
                        ->color(fn ($state) => $state ? 'success' : 'warning'),
                    TextEntry::make('scheduler.name')->label('Dijadwalkan Oleh')->placeholder('—'),
                    TextEntry::make('transporter.company_name')->label('Perusahaan'),
                    TextEntry::make('vehicle.registration_number')->label('NOPOL')->weight(FontWeight::Bold),
                    TextEntry::make('rejection_reason')->label('Alasan Penolakan')->placeholder('—')->columnSpanFull(),
                ]),
            Section::make('Dokumen Kendaraan')
                ->columnSpanFull()
                ->description('Dokumen wajib harus terverifikasi sebelum jadwal pemeriksaan dapat ditetapkan.')
                ->schema([
                    RepeatableEntry::make('vehicle.documents')
                        ->hiddenLabel()
                        ->contained(false)
                        ->placeholder('Tidak ada dokumen')
                        ->table([
                            TableColumn::make('Jenis'),
                            TableColumn::make('Nomor'),
                            TableColumn::make('Kedaluwarsa'),
                            TableColumn::make('Status'),
                            TableColumn::make('Berkas'),
                            TableColumn::make('Aksi Verifikasi'),
                        ])
                        ->schema([
                            TextEntry::make('documentType.name')->label('Jenis')->badge(),
                            TextEntry::make('document_number')->label('Nomor')->placeholder('—'),
                            TextEntry::make('expires_at')->label('Kedaluwarsa')->date('d M Y')->placeholder('—'),
                            TextEntry::make('verification_status')
                                ->label('Status')
                                ->badge()
                                ->formatStateUsing(fn ($state) => $state->label())
                                ->color(fn ($state) => match ($state) {
                                    DocumentVerificationStatus::Verified => 'success',
                                    DocumentVerificationStatus::Pending => 'warning',
                                    DocumentVerificationStatus::Rejected => 'danger',
                                }),
                            Actions::make([
                                Action::make('preview_document')
                                    ->label('Pratinjau')
                                    ->icon('heroicon-o-eye')
                                    ->color('gray')
                                    ->url(fn (VehicleDocument $record): string => route('documents.preview', $record))
                                    ->openUrlInNewTab(),
                                Action::make('download_document')
                                    ->label('Unduh')
                                    ->icon('heroicon-o-arrow-down-tray')
                                    ->color('gray')
                                    ->url(fn (VehicleDocument $record): string => route('documents.download', $record)),
                            ]),
                            Actions::make([
                                Action::make('verify_document')
                                    ->label('Verifikasi')
                                    ->icon('heroicon-o-check-circle')
                                    ->color('success')
                                    ->requiresConfirmation()
                                    ->modalHeading('Verifikasi dokumen ini?')
                                    ->visible(fn (VehicleDocument $record): bool => $record->replaced_by_id === null
                                        && $record->verification_status === DocumentVerificationStatus::Pending
                                        && auth()->user()->can('verify', $record))
                                    ->action(function (VehicleDocument $record): void {
                                        app(VerifyVehicleDocument::class)->execute($record, auth()->user());

                                        Notification::make()
                                            ->title('Dokumen diverifikasi')
                                            ->success()
                                            ->send();
                                    }),
                                Action::make('reject_document')
                                    ->label('Tolak')
                                    ->icon('heroicon-o-x-circle')
                                    ->color('danger')
                                    ->visible(fn (VehicleDocument $record): bool => $record->replaced_by_id === null
                                        && $record->verification_status === DocumentVerificationStatus::Pending
                                        && auth()->user()->can('verify', $record))
                                    ->schema([
                                        Textarea::make('reason')
                                            ->label('Alasan Penolakan')
                                            ->required()
                                            ->maxLength(1000),
                                    ])
                                    ->action(function (VehicleDocument $record, array $data): void {
                                        app(RejectVehicleDocument::class)->execute($record, auth()->user(), $data['reason']);

                                        Notification::make()
                                            ->title('Dokumen ditolak')
                                            ->success()
                                            ->send();
                                    }),
                            ]),
                        ]),
                ])
                ->visible(fn ($record) => $record->vehicle->documents->isNotEmpty()),
            Section::make('Temuan')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('findings')
                        ->hiddenLabel()
                        ->contained(false)
                        ->placeholder('Tidak ada temuan')
                        ->table([
                            TableColumn::make('Fungsi'),
                            TableColumn::make('Jenis Temuan (Checklist)'),
                            TableColumn::make('Deskripsi'),
                            TableColumn::make('Status'),
                            TableColumn::make('Batas Dispensasi'),
                            TableColumn::make('Dibuat Oleh'),
                            TableColumn::make('Waktu'),
                        ])
                        ->schema([
                            TextEntry::make('department')
                                ->label('Fungsi')
                                ->badge()
                                ->formatStateUsing(fn ($state) => $state->label()),
                            TextEntry::make('inspectionResult.item.label')
                                ->label('Jenis Temuan')
                                ->placeholder('—')
                                ->formatStateUsing(fn ($state, $record) => $record->inspectionResult?->item
                                    ? $record->inspectionResult->item->item_code.' — '.$record->inspectionResult->item->label
                                    : null),
                            TextEntry::make('description')->label('Deskripsi'),
                            TextEntry::make('status')
                                ->label('Status')
                                ->badge()
                                ->formatStateUsing(fn ($state) => $state->label())
                                ->color(fn ($state) => $state->color()),
                            TextEntry::make('dispensation_due_date')
                                ->label('Batas Dispensasi')
                                ->date('d M Y')
                                ->placeholder('—')
                                ->badge()
                                ->color(fn ($state, $record): string => $record->isDispensationExpired() ? 'danger' : 'warning'),
                            TextEntry::make('creator.name')->label('Dibuat Oleh')->placeholder('—'),
                            TextEntry::make('created_at')->label('Waktu')->dateTime('d M Y H:i'),
                        ])
                        ->columns(7),
                ])
                ->visible(fn ($record) => $record->findings->isNotEmpty()),
            Section::make('Hasil Pemeriksaan')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('inspectionResults')
                        ->contained(false)
                        ->hiddenLabel()
                        ->table([
                            TableColumn::make('Pemerikasaan'),
                            TableColumn::make('Hasil'),
                            TableColumn::make('Bukti Pemeriksaan'),
                            TableColumn::make('Diperiksa Oleh'),
                            TableColumn::make('Diperiksa Pada'),
                        ])
                        ->schema([
                            TextEntry::make('item.label')->label('Butir'),
                            TextEntry::make('result')->badge()->formatStateUsing(fn ($state) => $state->label()),
                            RepeatableEntry::make('attachments')
                                ->hiddenLabel()
                                ->contained(false)
                                ->placeholder('—')
                                ->schema([
                                    TextEntry::make('original_filename')
                                        ->hiddenLabel()
                                        ->formatStateUsing(fn ($state, $record) => $state.' ('.number_format($record->file_size / 1024, 0).' KB)'),
                                    Actions::make([
                                        Action::make('preview')
                                            ->label('Pratinjau')
                                            ->icon('heroicon-o-eye')
                                            ->color('gray')
                                            ->url(fn ($record): string => route('inspection-attachments.preview', $record))
                                            ->openUrlInNewTab(),
                                        Action::make('download')
                                            ->label('Unduh')
                                            ->icon('heroicon-o-arrow-down-tray')
                                            ->color('gray')
                                            ->url(fn ($record): string => route('inspection-attachments.download', $record)),
                                        Action::make('delete_attachment')
                                            ->label('Hapus')
                                            ->icon('heroicon-o-trash')
                                            ->color('danger')
                                            ->requiresConfirmation()
                                            ->modalHeading('Hapus bukti pemeriksaan?')
                                            ->visible(fn ($record): bool => $record->result?->department() !== null
                                                && auth()->user()->can($record->result->department()->inspectPermission()))
                                            ->action(fn ($record) => app(InspectionAttachmentService::class)
                                                ->delete($record, auth()->user())),
                                    ]),
                                ])
                                ->columns(2),
                            TextEntry::make('inspector.name')->label('Pemeriksa'),
                            TextEntry::make('inspected_at')->label('Waktu')->dateTime('d M Y H:i'),
                        ])
                        ->columns(5),
                ])
                ->visible(fn ($record) => $record->inspectionResults->isNotEmpty()),

            Section::make('Riwayat Approval')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('approvals')
                        ->hiddenLabel()
                        ->columnSpanFull()
                        ->contained(false)
                        ->table([
                            TableColumn::make('Fungsi'),
                            TableColumn::make('Keputusan'),
                            TableColumn::make('Oleh'),
                            TableColumn::make('Waktu'),
                            TableColumn::make('Catatan'),
                        ])
                        ->schema([
                            TextEntry::make('department')->label('Fungsi')->badge()->formatStateUsing(fn ($state) => $state->label()),
                            TextEntry::make('decision')->badge()
                                ->formatStateUsing(fn ($state) => $state->label())
                                ->color(fn ($state) => $state->value === 'approved' ? 'success' : 'danger'),
                            TextEntry::make('approver.name')->label('Oleh'),
                            TextEntry::make('decided_at')->label('Waktu')->dateTime('d M Y H:i'),
                            TextEntry::make('notes')->label('Catatan')->placeholder('—')->columnSpanFull(),
                        ]),
                ])
                ->visible(fn ($record) => $record->approvals->isNotEmpty()),
            Section::make('KIM Terbit')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('kimCard.kim_number')->label('Nomor KIM'),
                    TextEntry::make('kimCard.issued_at')->label('Terbit')->dateTime('d M Y'),
                    TextEntry::make('kimCard.expires_at')->label('Berlaku Hingga')->date('d M Y'),
                ])
                ->visible(fn ($record) => $record->kimCard !== null),
        ]);
    }

    protected function getHeaderActions(): array
    {
        $actions = [
            Action::make('inspect')
                ->label('Isi Checklist Pemeriksaan')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('primary')
                ->visible(fn (): bool => $this->record->isInspectionDue()
                    && in_array(
                        $this->record->status,
                        [ApplicationStatus::Submitted, ApplicationStatus::Inspection, ApplicationStatus::RevisionRequired],
                        true
                    ) && (
                        auth()->user()->can('inspect_hsse')
                        || auth()->user()->can('inspect_distribusi')
                        || auth()->user()->can('inspect_qq')
                    ))
                ->url(fn (): string => KimApplicationResource::getUrl('inspect', ['record' => $this->record])),
            Action::make('schedule')
                ->label(fn (): string => $this->record->inspection_scheduled_at
                    ? 'Reschedule Pemeriksaan'
                    : 'Tetapkan Tanggal Pemeriksaan')
                ->icon('heroicon-o-calendar-days')
                ->color('gray')
                ->visible(fn (): bool => in_array(
                    $this->record->status,
                    [ApplicationStatus::Submitted, ApplicationStatus::Inspection],
                    true
                ) && auth()->user()->can('schedule_inspection'))
                ->schema([
                    DateTimePicker::make('inspection_scheduled_at')
                        ->label('Tanggal & Waktu Pelaksanaan Pemeriksaan')
                        ->required()
                        ->seconds(false),
                ])
                ->fillForm(fn (): array => [
                    'inspection_scheduled_at' => $this->record->inspection_scheduled_at,
                ])
                ->action(function (array $data): void {
                    // Dokumen wajib harus terverifikasi sebelum pemeriksaan dijadwalkan.
                    try {
                        app(KimApplicationService::class)
                            ->ensureRequiredDocumentsVerified($this->record->vehicle);
                    } catch (ValidationException $e) {
                        $reason = app(ActivityLogger::class)->firstError($e);

                        app(ActivityLogger::class)->failureFromValidation(
                            'inspection',
                            $this->record,
                            $e,
                            ['action' => 'schedule'],
                        );

                        Notification::make()
                            ->title('Dokumen belum terverifikasi')
                            ->body($reason)
                            ->danger()
                            ->send();

                        return;
                    }

                    $isReschedule = $this->record->inspection_scheduled_at !== null;

                    $this->record->update([
                        'inspection_scheduled_at' => $data['inspection_scheduled_at'],
                        'inspection_scheduled_by' => auth()->id(),
                    ]);

                    $scheduledAt = Carbon::parse($data['inspection_scheduled_at'])->translatedFormat('d M Y H:i');

                    AppNotifier::notifyTransporter(
                        $this->record->transporter_id,
                        new InspectionScheduled(
                            $this->record->application_number,
                            $this->record->vehicle->registration_number,
                            $scheduledAt,
                        ),
                    );

                    AppNotifier::notifyStaffWithPermission(
                        'schedule_inspection',
                        new InspectionScheduledForStaff(
                            $this->record->application_number,
                            $this->record->vehicle->registration_number,
                            $scheduledAt,
                            $isReschedule,
                            (string) $this->record->getRouteKey(),
                        ),
                    );

                    Notification::make()
                        ->title($isReschedule
                            ? 'Jadwal pemeriksaan diperbarui'
                            : 'Jadwal pemeriksaan ditetapkan')
                        ->success()
                        ->send();
                }),
        ];

        // Dispensasi FTM tidak menghalangi approval selama batasnya belum terlewati.
        $hasOpenFindings = $this->record->findings
            ->contains(fn ($finding) => $finding->blocksApproval());

        $approvedDepartments = $this->record->approvals
            ->where('decision', ApprovalDecision::Approved)
            ->pluck('department')
            ->map(fn ($department) => $department->value)
            ->unique()
            ->values();

        $functionalApprovalsComplete = collect(Department::inspectingDepartments())
            ->every(fn (Department $department) => $approvedDepartments->contains($department->value));

        $rsdApprovalComplete = $approvedDepartments->contains(Department::Rsd->value);
        $preFinalApprovalsComplete = $functionalApprovalsComplete && $rsdApprovalComplete;

        foreach (Department::approvableDepartments() as $department) {
            $actions[] = $this->makeApprovalAction($department, ApprovalDecision::Approved, $hasOpenFindings, $functionalApprovalsComplete, $preFinalApprovalsComplete, $approvedDepartments);
            $actions[] = $this->makeApprovalAction($department, ApprovalDecision::Rejected, $hasOpenFindings, $functionalApprovalsComplete, $preFinalApprovalsComplete, $approvedDepartments);
        }

        $actions[] = $this->makeApprovalAction(Department::Ftm, ApprovalDecision::Approved, $hasOpenFindings, $functionalApprovalsComplete, $preFinalApprovalsComplete, $approvedDepartments);
        $actions[] = $this->makeApprovalAction(Department::Ftm, ApprovalDecision::Rejected, $hasOpenFindings, $functionalApprovalsComplete, $preFinalApprovalsComplete, $approvedDepartments);

        // Pemberian dispensasi baru kini melalui menu "Pengajuan KIM Sementara"
        // agar alur selalu dimulai dari permohonan transportir.

        $actions[] = Action::make('inspection_report')
            ->label('Laporan Pemeriksaan')
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->visible(fn (): bool => $this->record->inspectionResults->isNotEmpty())
            ->url(fn (): string => route('inspection-report.preview', $this->record))
            ->openUrlInNewTab();

        $actions[] = Action::make('berita_acara')
            ->label('Berita Acara')
            ->icon('heroicon-o-document-text')
            ->color('gray')
            ->visible(fn (): bool => app(BeritaAcaraService::class)->isEligible($this->record))
            ->url(fn (): string => route('berita-acara.download', $this->record))
            ->openUrlInNewTab();

        // KIM sementara: setelah FTM menyetujui dispensasi, penerbitan dapat
        // dilakukan langsung dari sini tanpa menunggu approval pengawas.
        $actions[] = Action::make('issue_provisional')
            ->label('Terbitkan KIM Sementara')
            ->icon('heroicon-o-clock')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Terbitkan KIM Sementara')
            ->modalDescription('Approval FTM dicatat dan KIM sementara langsung diterbitkan.')
            ->visible(fn (): bool => $this->record->canIssueProvisional()
                && auth()->user()->can('issue_kim'))
            ->action(function (): void {
                try {
                    app(KimIssuanceService::class)->issueProvisional($this->record, auth()->user());
                } catch (ValidationException $e) {
                    $reason = app(ActivityLogger::class)->firstError($e);

                    app(ActivityLogger::class)->failureFromValidation(
                        'kim',
                        $this->record,
                        $e,
                        ['action' => 'issue_provisional'],
                    );

                    Notification::make()
                        ->title('Penerbitan gagal')
                        ->body($reason)
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('KIM sementara diterbitkan')
                    ->success()
                    ->send();

                // Segarkan halaman agar tombol approval/terbit dan riwayat ikut terbarui
                // tanpa perlu reload manual.
                $this->redirect(
                    KimApplicationResource::getUrl('view', ['record' => $this->record]),
                    navigate: true,
                );
            });

        $actions[] = Action::make('issue_kim')
            ->label('Terbitkan KIM')
            ->icon('heroicon-o-identification')
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (): bool => $this->record->status === ApplicationStatus::Approved
                && auth()->user()->can('issue_kim'))
            ->action(function (): void {
                try {
                    app(KimIssuanceService::class)->issue($this->record);
                } catch (ValidationException $e) {
                    $reason = app(ActivityLogger::class)->firstError($e);

                    app(ActivityLogger::class)->failureFromValidation(
                        'kim',
                        $this->record,
                        $e,
                        ['action' => 'issue'],
                    );

                    Notification::make()
                        ->title('Penerbitan gagal')
                        ->body($reason)
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('KIM berhasil diterbitkan')
                    ->success()
                    ->send();

                // Segarkan halaman agar tombol approval/terbit dan riwayat ikut terbarui
                // tanpa perlu reload manual.
                $this->redirect(
                    KimApplicationResource::getUrl('view', ['record' => $this->record]),
                    navigate: true,
                );
            });

        return $actions;
    }

    /**
     * @param  Collection<int, string>  $approvedDepartments
     */
    private function makeApprovalAction(
        Department $department,
        ApprovalDecision $decision,
        bool $hasOpenFindings,
        bool $functionalApprovalsComplete,
        bool $preFinalApprovalsComplete,
        Collection $approvedDepartments,
    ): Action {
        $permission = $department === Department::Ftm ? 'approve_final' : 'approve_'.$department->value;
        $isApprove = $decision === ApprovalDecision::Approved;
        $label = $department === Department::Ftm ? 'Approval Final (FTM)' : 'Approval '.$department->label();
        $label = $isApprove ? $label : 'Tolak — '.$department->label();

        // RSD menunggu ketiga fungsi; FTM menunggu ketiga fungsi + RSD.
        $gateOpen = match ($department) {
            Department::Rsd => $functionalApprovalsComplete,
            Department::Ftm => $preFinalApprovalsComplete,
            default => true,
        };

        return Action::make(($isApprove ? 'approve_' : 'reject_').$department->value)
            ->label($label)
            ->icon($isApprove ? 'heroicon-o-check-badge' : 'heroicon-o-x-circle')
            ->color($isApprove ? 'gray' : 'danger')
            ->visible(fn (): bool => auth()->user()->can($permission)
                && in_array(
                    $this->record->status,
                    [ApplicationStatus::Inspection, ApplicationStatus::RevisionRequired, ApplicationStatus::PendingApproval],
                    true
                )
                && ! $approvedDepartments->contains($department->value)
                && ! $hasOpenFindings
                && $gateOpen)
            ->schema([
                Textarea::make('notes')
                    ->label($isApprove ? 'Catatan (opsional)' : 'Alasan Penolakan (wajib)')
                    ->required(! $isApprove)
                    ->rows(2)
                    ->maxLength(1000),
            ])
            ->action(function (array $data) use ($department, $decision): void {
                try {
                    app(ApprovalService::class)->decide(
                        $this->record,
                        $department,
                        $decision,
                        auth()->user(),
                        $data['notes'] ?? null,
                    );
                } catch (ValidationException $e) {
                    $reason = app(ActivityLogger::class)->firstError($e);

                    app(ActivityLogger::class)->failureFromValidation(
                        'approval',
                        $this->record,
                        $e,
                        [
                            'department' => $department->value,
                            'decision' => $decision->value,
                        ],
                    );

                    Notification::make()
                        ->title('Proses gagal')
                        ->body($reason)
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title($decision === ApprovalDecision::Approved
                        ? 'Approval '.$department->label().' tercatat'
                        : 'Pengajuan ditolak')
                    ->success()
                    ->send();

                // Segarkan halaman agar tombol approval/terbit dan riwayat ikut terbarui
                // tanpa perlu reload manual.
                $this->redirect(
                    KimApplicationResource::getUrl('view', ['record' => $this->record]),
                    navigate: true,
                );
            });
    }
}
