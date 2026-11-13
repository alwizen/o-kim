<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Enums\InternalRole;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleForm
{
    /**
     * Permission yang hanya boleh diberikan oleh super admin.
     */
    private const PRIVILEGED_PERMISSIONS = [
        'access_admin_panel',
        'manage_users',
        'manage_roles',
        'manage_settings',
        'manage_notification_settings',
        'issue_kim',
        'approve_final',
    ];

    /**
     * Sembunyikan permission sensitif dari pemegang manage_roles yang bukan super admin.
     *
     * @param  array<string, string>  $options
     * @return array<string, string>
     */
    private static function visiblePermissionOptions(array $options): array
    {
        if (auth()->user()?->hasRole(InternalRole::SuperAdmin->value)) {
            return $options;
        }

        return collect($options)
            ->reject(fn (string $label, string $name) => in_array($name, self::PRIVILEGED_PERMISSIONS, true))
            ->all();
    }

    /**
     * Daftar permission yang tersedia, dikelompokkan per kategori agar mudah dipilih.
     *
     * @return array<string, array<string, string>>
     */
    public static function groupedPermissions(): array
    {
        $all = Permission::query()->orderBy('name')->pluck('name', 'name')->all();

        $groups = [
            'Umum & Master Data' => [
                'access_admin_panel',
                'manage_users',
                'manage_roles',
                'manage_notification_settings',
                'manage_settings',
                'manage_document_types',
                'manage_transporters',
                'review_transporter_registrations',
                'manage_vehicles',
                'view_audit_logs',
                'view_activity_logs',
            ],
            'Dokumen & Checklist' => [
                'verify_documents',
                'manage_checklists',
                'schedule_inspection',
            ],
            'Pengajuan & Pemeriksaan (Checklist)' => [
                'view_applications',
                'view_progress_stepper',
                'inspect_hsse',
                'inspect_distribusi',
                'inspect_qq',
                'manage_findings',
                'view_findings',
            ],
            'Approval' => [
                'approve_hsse',
                'approve_distribusi',
                'approve_qq',
                'approve_rsd',
                'approve_final',
                'verify_finding_actions',
                'approve_tad_findings',
                'dispensate_findings',
            ],
            'KIM' => [
                'issue_kim',
                'view_kim_cards',
            ],
        ];

        $grouped = [];

        foreach ($groups as $label => $names) {
            foreach ($names as $name) {
                if (isset($all[$name])) {
                    $grouped[$label][$name] = Str::of($name)
                        ->replace('_', ' ')
                        ->title()
                        ->toString();
                    unset($all[$name]);
                }
            }
        }

        // Permission non-bawaan (dibuat manual) tetap muncul di grup "Lainnya".
        foreach ($all as $name => $_) {
            $grouped['Lainnya'][$name] = Str::of($name)->replace('_', ' ')->title()->toString();
        }

        return $grouped;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Detail Role')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nama Role')
                        ->required()
                        ->maxLength(125)
                        ->alphaDash()
                        ->unique(ignoreRecord: true)
                        ->helperText('Huruf, angka, dan garis bawah. Contoh: pengawas_hsse'),
                    Toggle::make('is_builtin')
                        ->label('Role Bawaan')
                        ->disabled()
                        ->dehydrated(false)
                        ->formatStateUsing(fn (?Role $record): bool => $record !== null
                            && collect(InternalRole::values())->contains($record->name)),
                ]),

            ...collect(self::groupedPermissions())
                ->map(fn (array $options, string $group) => Section::make($group)
                    ->description(self::groupDescription($group))
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        CheckboxList::make('permissions_'.Str::slug($group, '_'))
                            ->hiddenLabel()
                            ->options(self::visiblePermissionOptions($options))
                            ->descriptions(collect(self::visiblePermissionOptions($options))
                                ->mapWithKeys(fn (string $label, string $name) => [
                                    $name => self::permissionDescription($name),
                                ])
                                ->all())
                            ->columns(3)
                            ->columnSpanFull()
                            ->bulkToggleable()
                            ->formatStateUsing(fn ($component) => $component
                                ->getRecord()
                                ?->permissions
                                ->pluck('name')
                                ->intersect(array_keys(self::visiblePermissionOptions($options)))
                                ->values()
                                ->all() ?? [])
                            ->dehydrated(false)
                            ->saveRelationshipsUsing(function ($component, $state) use ($options) {
                                $record = $component->getRecord();

                                if (! $record) {
                                    return;
                                }

                                $allowed = array_keys(self::visiblePermissionOptions($options));

                                $others = $record->permissions
                                    ->pluck('name')
                                    ->reject(fn (string $name) => array_key_exists($name, $options));

                                $record->syncPermissions(
                                    $others->merge(collect((array) $state)->intersect($allowed))->values()->all()
                                );
                            }),
                    ]))
                ->values()
                ->all(),

        ]);
    }

    public static function groupDescription(string $group): string
    {
        return match ($group) {
            'Umum & Master Data' => 'Akses panel, pengguna, role, pengaturan, dan data induk.',
            'Dokumen & Checklist' => 'Verifikasi dokumen, kelola checklist, dan jadwal pemeriksaan.',
            'Pengajuan & Pemeriksaan (Checklist)' => 'Lihat pengajuan dan mengisi checklist pemeriksaan (TAD).',
            'Approval' => 'Wewenang approval pengajuan dan verifikasi temuan.',
            'KIM' => 'Penerbitan dan melihat kartu KIM.',
            default => 'Permission lainnya.',
        };
    }

    public static function permissionDescription(string $name): string
    {
        return match ($name) {
            'access_admin_panel' => 'Boleh masuk panel admin',
            'manage_users' => 'Kelola pengguna internal',
            'manage_roles' => 'Kelola role & permission',
            'manage_notification_settings' => 'Kelola penerima notifikasi email',
            'manage_settings' => 'Kelola pengaturan aplikasi',
            'manage_document_types' => 'Kelola jenis dokumen',
            'manage_transporters' => 'Kelola data transportir',
            'review_transporter_registrations' => 'Verifikasi registrasi transportir',
            'manage_vehicles' => 'Kelola armada',
            'view_audit_logs' => 'Lihat audit log',
            'view_activity_logs' => 'Lihat log aktivitas pengguna',
            'verify_documents' => 'Verifikasi dokumen',
            'manage_checklists' => 'Kelola butir checklist',
            'schedule_inspection' => 'Tetapkan jadwal pemeriksaan',
            'view_applications' => 'Lihat pengajuan KIM',
            'view_progress_stepper' => 'Lihat stepper progres pengajuan',
            'inspect_hsse', 'inspect_distribusi', 'inspect_qq' => 'Isi checklist fungsi terkait',
            'manage_findings' => 'Buat temuan saat checklist',
            'view_findings' => 'Lihat temuan',
            'approve_hsse', 'approve_distribusi', 'approve_qq' => 'Approval pengajuan fungsi terkait',
            'approve_rsd' => 'Approval sebelum approval final (Pengawas RSD)',
            'approve_final' => 'Approval final (FTM)',
            'verify_finding_actions' => 'Verifikasi tindak lanjut temuan',
            'approve_tad_findings' => 'Verifikasi temuan dari user TAD',
            'dispensate_findings' => 'Beri/perpanjang/cabut dispensasi temuan (khusus FTM)',
            'issue_kim' => 'Terbitkan KIM',
            'view_kim_cards' => 'Lihat kartu KIM',
            default => 'Permission tambahan',
        };
    }
}
