<?php

namespace App\Filament\Resources\ActivityLogs\Tables;

use App\Enums\InternalRole;
use App\Models\ActivityLog;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivityLogsTable
{
    /**
     * Label dan warna badge untuk setiap kelompok event.
     *
     * @var array<string, array{label: string, color: string}>
     */
    public const EVENTS = [
        'login' => ['label' => 'Masuk', 'color' => 'success'],
        'login_failed' => ['label' => 'Gagal Masuk', 'color' => 'danger'],
        'logout' => ['label' => 'Keluar', 'color' => 'gray'],
        'registered' => ['label' => 'Pendaftaran', 'color' => 'info'],
        'inspection' => ['label' => 'Checklist', 'color' => 'info'],
        'inspection.completed' => ['label' => 'Checklist Selesai', 'color' => 'info'],
        'application' => ['label' => 'Pengajuan', 'color' => 'info'],
        'approval' => ['label' => 'Approval', 'color' => 'info'],
        'approval.approved' => ['label' => 'Approval Disetujui', 'color' => 'success'],
        'approval.rejected' => ['label' => 'Approval Ditolak', 'color' => 'danger'],
        'finding' => ['label' => 'Tindak Lanjut', 'color' => 'info'],
        'dispensation' => ['label' => 'Dispensasi', 'color' => 'info'],
        'kim' => ['label' => 'Penerbitan KIM', 'color' => 'info'],
        'document' => ['label' => 'Dokumen', 'color' => 'info'],
        'user' => ['label' => 'Pengguna', 'color' => 'info'],
        'finding.action_submitted' => ['label' => 'Tindak Lanjut Dikirim', 'color' => 'warning'],
        'finding.action_verified' => ['label' => 'Tindak Lanjut Diverifikasi', 'color' => 'success'],
        'dispensation.requested' => ['label' => 'Dispensasi Diajukan', 'color' => 'warning'],
        'dispensation.request_approved' => ['label' => 'Dispensasi Disetujui', 'color' => 'success'],
        'dispensation.request_rejected' => ['label' => 'Dispensasi Ditolak', 'color' => 'danger'],
        'dispensation.granted' => ['label' => 'Dispensasi Diberikan', 'color' => 'warning'],
        'dispensation.extended' => ['label' => 'Dispensasi Diperpanjang', 'color' => 'info'],
        'dispensation.reason_updated' => ['label' => 'Alasan Dispensasi Diubah', 'color' => 'info'],
        'dispensation.revoked' => ['label' => 'Dispensasi Dicabut', 'color' => 'danger'],
        'kim.issued' => ['label' => 'KIM Terbit', 'color' => 'success'],
        'berita_acara.finalized' => ['label' => 'Berita Acara Dibekukan', 'color' => 'success'],
        'user.activated' => ['label' => 'Akun Diaktifkan', 'color' => 'success'],
        'user.deactivated' => ['label' => 'Akun Dinonaktifkan', 'color' => 'danger'],
        'user.registration_rejected' => ['label' => 'Pendaftaran Internal Ditolak', 'color' => 'danger'],
        'user.transporter_approved' => ['label' => 'Transportir Disetujui', 'color' => 'success'],
        'user.transporter_rejected' => ['label' => 'Transportir Ditolak', 'color' => 'danger'],
        'document.uploaded' => ['label' => 'Dokumen Diunggah', 'color' => 'info'],
        'document.deleted' => ['label' => 'Dokumen Dihapus', 'color' => 'danger'],
        'deleted' => ['label' => 'Data Dihapus', 'color' => 'danger'],
    ];

    /**
     * Nama role yang ramah dibaca. Transportir ditulis apa adanya karena bukan
     * role internal (tidak ada di InternalRole).
     */
    public static function roleLabel(?string $role): string
    {
        if ($role === null || $role === '') {
            return '—';
        }

        if ($role === 'transportir') {
            return 'Transportir';
        }

        return InternalRole::tryFrom($role)?->label() ?? $role;
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i:s')
                    ->sortable(),
                TextColumn::make('user_name')
                    ->label('Pengguna')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('user_role')
                    ->label('Role')
                    ->badge()
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => self::roleLabel($state))
                    ->color(fn (?string $state): string => $state === 'transportir' ? 'info' : 'gray'),
                TextColumn::make('event')
                    ->label('Aktivitas')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::EVENTS[$state]['label'] ?? $state)
                    ->color(fn (string $state): string => self::EVENTS[$state]['color'] ?? 'gray')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === ActivityLog::STATUS_FAILED ? 'Gagal' : 'Berhasil')
                    ->color(fn (?string $state): string => $state === ActivityLog::STATUS_FAILED ? 'danger' : 'success')
                    ->sortable(),
                TextColumn::make('subject_label')
                    ->label('Nomor / Objek')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('description')
                    ->label('Keterangan')
                    ->wrap()
                    ->limit(90)
                    ->tooltip(fn (?string $state): ?string => $state),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('user_agent')
                    ->label('User Agent')
                    ->limit(40)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        ActivityLog::STATUS_SUCCESS => 'Berhasil',
                        ActivityLog::STATUS_FAILED => 'Gagal',
                    ]),
                SelectFilter::make('event')
                    ->label('Aktivitas')
                    // ->multiple()
                    ->options(collect(self::EVENTS)->mapWithKeys(
                        fn (array $meta, string $event) => [$event => $meta['label']]
                    )->all()),
                SelectFilter::make('user_id')
                    ->label('Pengguna')
                    ->searchable()
                    ->optionsLimit(50)
                    ->options(fn (): array => User::query()
                        ->orderBy('name')
                        ->limit(200)
                        ->pluck('name', 'id')
                        ->all()),
                Filter::make('created_at')
                    ->label('Rentang Tanggal')
                    ->schema([
                        DatePicker::make('from')->label('Dari')->native(false),
                        DatePicker::make('until')->label('Sampai')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when(
                            filled($data['from'] ?? null),
                            fn (Builder $q) => $q->whereDate('created_at', '>=', $data['from']),
                        )
                        ->when(
                            filled($data['until'] ?? null),
                            fn (Builder $q) => $q->whereDate('created_at', '<=', $data['until']),
                        )),
            ])
            ->emptyStateHeading('Belum ada aktivitas')
            ->emptyStateDescription('Aktivitas pengguna akan tercatat otomatis di sini.');
    }
}
