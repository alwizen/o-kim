<?php

namespace App\Filament\Resources\KimApplications\Pages;

use App\Enums\ApplicationStatus;
use App\Enums\ApprovalDecision;
use App\Enums\Department;
use App\Enums\FindingStatus;
use App\Filament\Resources\KimApplications\KimApplicationResource;
use App\Notifications\InspectionScheduled;
use App\Services\Approval\ApprovalService;
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
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ViewKimApplication extends ViewRecord
{
    protected static string $resource = KimApplicationResource::class;

    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->loadMissing([
            'findings.inspectionResult.item',
            'findings.creator',
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
                                ->color(fn ($state) => match ($state) {
                                    FindingStatus::Open => 'danger',
                                    FindingStatus::SubmittedForVerification => 'warning',
                                    FindingStatus::Verified => 'success',
                                    FindingStatus::Rejected => 'danger',
                                }),
                            TextEntry::make('creator.name')->label('Dibuat Oleh')->placeholder('—'),
                            TextEntry::make('created_at')->label('Waktu')->dateTime('d M Y H:i'),
                        ])
                        ->columns(6),
                ])
                ->visible(fn ($record) => $record->findings()->exists()),
            Section::make('Hasil Pemeriksaan')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('inspectionResults')
                        ->contained(false)
                        ->hiddenLabel()
                        ->table([
                            TableColumn::make('Pemerikasaan'),
                            TableColumn::make('Hasil'),
                            TableColumn::make('Diperiksa Oleh'),
                            TableColumn::make('Diperiksa Pada'),
                        ])
                        ->schema([
                            TextEntry::make('item.label')->label('Butir'),
                            TextEntry::make('result')->badge()->formatStateUsing(fn ($state) => $state->label()),
                            TextEntry::make('inspector.name')->label('Pemeriksa'),
                            TextEntry::make('inspected_at')->label('Waktu')->dateTime('d M Y H:i'),
                        ])
                        ->columns(4),
                ])
                ->visible(fn ($record) => $record->inspectionResults()->exists()),

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
                ->visible(fn ($record) => $record->approvals()->exists()),
            Section::make('KIM Terbit')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('kimCard.kim_number')->label('Nomor KIM'),
                    TextEntry::make('kimCard.issued_at')->label('Terbit')->dateTime('d M Y'),
                    TextEntry::make('kimCard.expires_at')->label('Berlaku Hingga')->date('d M Y'),
                ])
                ->visible(fn ($record) => $record->kimCard()->exists()),
        ]);
    }

    protected function getHeaderActions(): array
    {
        $actions = [
            Action::make('inspect')
                ->label('Isi Checklist Pemeriksaan')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('primary')
                ->visible(fn (): bool => in_array(
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
                ->label('Tetapkan Tanggal Pemeriksaan')
                ->icon('heroicon-o-calendar-days')
                ->color('gray')
                ->visible(fn (): bool => in_array(
                    $this->record->status,
                    [ApplicationStatus::Submitted, ApplicationStatus::Inspection],
                    true
                ) && auth()->user()->can('view_applications'))
                ->schema([
                    DateTimePicker::make('inspection_scheduled_at')
                        ->label('Tanggal & Waktu Pelaksanaan Pemeriksaan')
                        ->required()
                        ->seconds(false)
                        ->native(false),
                ])
                ->fillForm(fn (): array => [
                    'inspection_scheduled_at' => $this->record->inspection_scheduled_at,
                ])
                ->action(function (array $data): void {
                    $this->record->update([
                        'inspection_scheduled_at' => $data['inspection_scheduled_at'],
                        'inspection_scheduled_by' => auth()->id(),
                    ]);

                    AppNotifier::notifyTransporter(
                        $this->record->transporter_id,
                        new InspectionScheduled(
                            $this->record->application_number,
                            $this->record->vehicle->registration_number,
                            Carbon::parse($data['inspection_scheduled_at'])->translatedFormat('d M Y H:i'),
                        ),
                    );

                    Notification::make()
                        ->title('Jadwal pemeriksaan ditetapkan')
                        ->success()
                        ->send();
                }),
        ];

        foreach (Department::inspectingDepartments() as $department) {
            $actions[] = $this->makeApprovalAction($department, ApprovalDecision::Approved);
            $actions[] = $this->makeApprovalAction($department, ApprovalDecision::Rejected);
        }

        $actions[] = $this->makeApprovalAction(Department::Ftm, ApprovalDecision::Approved);
        $actions[] = $this->makeApprovalAction(Department::Ftm, ApprovalDecision::Rejected);

        $actions[] = Action::make('issue_kim')
            ->label('Terbitkan KIM')
            ->icon('heroicon-o-identification')
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (): bool => $this->record->status === ApplicationStatus::Approved
                && auth()->user()->can('approve_final'))
            ->action(function (): void {
                try {
                    app(KimIssuanceService::class)->issue($this->record);
                } catch (ValidationException $e) {
                    Notification::make()
                        ->title('Penerbitan gagal')
                        ->body(collect($e->errors())->flatten()->first())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('KIM berhasil diterbitkan')
                    ->success()
                    ->send();
            });

        return $actions;
    }

    private function makeApprovalAction(Department $department, ApprovalDecision $decision): Action
    {
        $permission = $department === Department::Ftm ? 'approve_final' : 'approve_'.$department->value;
        $isApprove = $decision === ApprovalDecision::Approved;
        $label = $department === Department::Ftm ? 'Approval Final (FTM)' : 'Approval '.$department->label();
        $label = $isApprove ? $label : 'Tolak — '.$department->label();

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
                && ! $this->record->approvals()
                    ->where('department', $department->value)
                    ->where('decision', 'approved')
                    ->exists())
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
                    Notification::make()
                        ->title('Proses gagal')
                        ->body(collect($e->errors())->flatten()->first())
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
            });
    }
}
