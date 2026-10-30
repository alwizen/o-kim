<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\AccountStatus;
use App\Enums\InternalRole;
use App\Filament\Resources\Roles\Schemas\RoleForm;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserForm
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
        'dispensate_findings',
    ];

    private static function actorIsSuperAdmin(): bool
    {
        return (bool) auth()->user()?->hasRole(
            InternalRole::SuperAdmin->value
        );
    }

    /**
     * Role bawaan enum + role kustom.
     */
    public static function roleOptions(): array
    {
        $enumOptions = InternalRole::options();

        // Super admin hanya boleh diberikan oleh super admin.
        if (! self::actorIsSuperAdmin()) {
            unset($enumOptions[InternalRole::SuperAdmin->value]);
        }

        $custom = Role::query()
            ->whereNotIn('name', array_keys(InternalRole::options()))
            ->where('name', '!=', 'transportir')
            ->pluck('name', 'name')
            ->all();

        return $enumOptions + $custom;
    }

    /**
     * Sembunyikan permission sensitif dari non-super-admin.
     */
    private static function filterPrivilegedPermissions(array $options): array
    {
        if (self::actorIsSuperAdmin()) {
            return $options;
        }

        return collect($options)
            ->reject(
                fn (string $label, string $name) => in_array(
                    $name,
                    self::PRIVILEGED_PERMISSIONS,
                    true
                )
            )
            ->all();
    }

    /**
     * Konfigurasi form user.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            /*
             * DATA AKUN
             * HP: 1 kolom
             * Tablet: 2 kolom
             * Desktop besar: 3 kolom
             */
            Section::make('Data Akun')
                ->columnSpanFull()
                ->columns([
                    'default' => 1,
                    'md' => 2,
                    'xl' => 3,
                ])
                ->schema([
                    TextInput::make('name')
                        ->label('Nama')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('jabatan')
                        ->label('Jabatan')
                        ->maxLength(100)
                        ->helperText(
                            'Jabatan fungsional, mis. Safety Officer. Berbeda dengan role sistem.'
                        ),

                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

                    TextInput::make('password')
                        ->label('Kata Sandi')
                        ->password()
                        ->revealable()
                        ->rules(
                            fn (?string $state): array => filled($state)
                                ? [
                                    Password::min(8)
                                        ->letters()
                                        ->mixedCase()
                                        ->numbers(),
                                ]
                                : []
                        )
                        ->required(
                            fn (string $operation): bool => $operation === 'create'
                        )
                        ->dehydrated(
                            fn (?string $state): bool => filled($state)
                        )
                        ->helperText(
                            fn (string $operation): ?string => $operation === 'edit'
                                ? 'Biarkan kosong bila tidak ingin mengubah kata sandi.'
                                : 'Minimal 8 karakter, kombinasi huruf besar, huruf kecil, dan angka.'
                        ),

                    TextInput::make('password_confirmation')
                        ->label('Konfirmasi Kata Sandi')
                        ->password()
                        ->revealable()
                        ->same('password')
                        ->required(
                            fn (string $operation): bool => $operation === 'create'
                        )
                        ->dehydrated(false),
                ]),

            /*
             * ROLE & STATUS
             * HP: 1 kolom
             * Tablet ke atas: 2 kolom
             */
            Section::make('Peran & Status')
                ->columnSpanFull()
                ->columns([
                    'default' => 1,
                    'md' => 2,
                ])
                ->schema([
                    CheckboxList::make('roles')
                        ->label('Role')
                        ->options(self::roleOptions())
                        ->descriptions(
                            collect(self::roleOptions())
                                ->mapWithKeys(
                                    fn (string $label, string $value) => [
                                        $value => $label,
                                    ]
                                )
                                ->all()
                        )
                        ->required()
                        ->columns([
                            'default' => 1,
                            'md' => 2,
                            'xl' => 3,
                        ])
                        ->columnSpanFull()
                        ->formatStateUsing(function ($component, $state) {
                            // Ambil role dari relasi Spatie saat edit.
                            $record = $component->getRecord();

                            if (! $record) {
                                return [];
                            }

                            $allowed = array_keys(self::roleOptions());

                            return $record->roles
                                ->pluck('name')
                                ->filter(
                                    fn (string $name) => in_array(
                                        $name,
                                        $allowed,
                                        true
                                    )
                                )
                                ->values()
                                ->all();
                        })
                        ->dehydrated(false)
                        ->saveRelationshipsUsing(function ($component, $state) {
                            $record = $component->getRecord();

                            if (! $record) {
                                return;
                            }

                            $allowed = array_keys(self::roleOptions());

                            $roles = collect((array) $state)
                                ->filter(
                                    fn ($name) => in_array(
                                        $name,
                                        $allowed,
                                        true
                                    )
                                )
                                ->values()
                                ->all();

                            $record->syncRoles($roles);
                        }),

                    Select::make('account_status')
                        ->label('Status Akun')
                        ->options(
                            collect(AccountStatus::cases())
                                ->mapWithKeys(
                                    fn (AccountStatus $status) => [
                                        $status->value => $status->label(),
                                    ]
                                )
                        )
                        ->default(AccountStatus::Approved->value)
                        ->required()
                        ->native(false),
                ]),

            /*
             * PERMISSION TAMBAHAN
             * HP: 1 kolom
             * Tablet: 2 kolom
             * Desktop besar: 3 kolom
             */
            ...collect(RoleForm::groupedPermissions())
                ->map(
                    fn (array $options, string $group) => Section::make(
                        'Permission Tambahan — '.$group
                    )
                        ->description(
                            'Beri akses tambahan di luar role (fleksibel).'
                        )
                        ->collapsed()
                        ->columnSpanFull()
                        ->columns([
                            'default' => 1,
                            'md' => 2,
                        ])
                        ->schema([
                            CheckboxList::make('extra_permissions_'.$group)
                                ->hiddenLabel()
                                ->options(
                                    self::filterPrivilegedPermissions($options)
                                )
                                ->descriptions(
                                    collect(
                                        self::filterPrivilegedPermissions($options)
                                    )
                                        ->mapWithKeys(
                                            fn (string $label, string $name) => [
                                                $name => RoleForm::permissionDescription(
                                                    $name
                                                ),
                                            ]
                                        )
                                        ->all()
                                )
                                ->columns([
                                    'default' => 1,
                                    'md' => 2,
                                    'xl' => 3,
                                ])
                                ->columnSpanFull()
                                ->bulkToggleable()
                                ->formatStateUsing(
                                    fn ($component) => $component
                                        ->getRecord()
                                        ?->permissions
                                        ->pluck('name')
                                        ->intersect(
                                            array_keys(
                                                self::filterPrivilegedPermissions(
                                                    $options
                                                )
                                            )
                                        )
                                        ->values()
                                        ->all() ?? []
                                )
                                ->dehydrated(false)
                                ->visible(
                                    fn () => (bool) auth()->user()?->can(
                                        'manage_users'
                                    )
                                )
                                ->saveRelationshipsUsing(
                                    function ($component, $state) use ($options) {
                                        $record = $component->getRecord();

                                        if (! $record) {
                                            return;
                                        }

                                        // Pertahankan permission di luar grup ini.
                                        $allowed = array_keys(
                                            self::filterPrivilegedPermissions(
                                                $options
                                            )
                                        );

                                        $others = $record->permissions
                                            ->pluck('name')
                                            ->reject(
                                                fn (string $name) => array_key_exists(
                                                    $name,
                                                    $options
                                                )
                                            );

                                        $record->syncPermissions(
                                            $others
                                                ->merge(
                                                    collect((array) $state)
                                                        ->intersect($allowed)
                                                )
                                                ->values()
                                                ->all()
                                        );
                                    }
                                ),
                        ])
                )
                ->values()
                ->all(),

        ]);
    }
}
