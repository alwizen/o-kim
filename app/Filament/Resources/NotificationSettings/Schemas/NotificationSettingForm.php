<?php

namespace App\Filament\Resources\NotificationSettings\Schemas;

use App\Enums\InternalRole;
use App\Enums\NotificationEvent;
use App\Models\NotificationSetting;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

class NotificationSettingForm
{
    /**
     * Opsi role penerima.
     *
     * @return array<string, string>
     */
    public static function roleOptions(): array
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->pluck('name', 'name')
            ->map(fn (string $name): string => InternalRole::tryFrom($name)?->label()
                ?? str($name)->replace('_', ' ')->title()->toString())
            ->all();
    }

    public static function roleDescription(string $role): string
    {
        return match ($role) {
            'super_admin' => 'Akses penuh sistem (termasuk pengaturan).',
            'admin_verifikasi' => 'Verifikasi pendaftaran, dokumen, dan kendaraan.',
            'hsse' => 'Petugas HSSE (TAD) — mengisi checklist HSSE.',
            'distribusi' => 'Petugas Distribusi (TAD) — mengisi checklist Distribusi.',
            'qq' => 'Petugas QQ (TAD) — mengisi checklist QQ.',
            'ftm' => 'Fuel Terminal Manager — approval final & penerbitan KIM.',
            'pengawas_hsse' => 'Pengawas HSSE — verifikasi temuan & approval HSSE.',
            'pengawas_distribusi' => 'Pengawas Distribusi — verifikasi temuan & approval Distribusi.',
            'pengawas_qq' => 'Pengawas QQ — verifikasi temuan & approval QQ.',
            'transportir' => 'PIC perusahaan transportir.',
            default => 'Role tambahan.',
        };
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Detail Notifikasi')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('label')
                        ->label('Notifikasi')
                        ->disabled()
                        ->dehydrated(false)
                        ->formatStateUsing(fn (?NotificationSetting $record): string => static::eventFor($record)?->label() ?? ''),
                    TextInput::make('event_key')
                        ->label('Kode Event')
                        ->disabled()
                        ->dehydrated(false),
                ]),

            Section::make('Pengaturan Email')
                ->columnSpanFull()
                ->description(fn (?NotificationSetting $record): string => static::eventFor($record)?->description() ?? '')
                ->schema([
                    Toggle::make('mail_enabled')
                        ->label('Kirim email untuk notifikasi ini')
                        ->default(true)
                        ->disabled(fn (?NotificationSetting $record): bool => static::eventFor($record)?->locked() ?? false)
                        ->dehydrated(fn (?NotificationSetting $record): bool => ! (static::eventFor($record)?->locked() ?? false))
                        ->helperText(fn (?NotificationSetting $record): string => (static::eventFor($record)?->locked() ?? false)
                            ? 'Selalu aktif — notifikasi keamanan akun tidak dapat dimatikan.'
                            : 'Lonceng notifikasi panel tetap aktif walau email dimatikan.'),

                    CheckboxList::make('roles')
                        ->label('Role penerima email')
                        ->options(self::roleOptions())
                        ->descriptions(collect(self::roleOptions())
                            ->mapWithKeys(fn (string $label, string $name) => [$name => self::roleDescription($name)])
                            ->all())
                        ->columns(3)
                        ->bulkToggleable()
                        ->disabled(fn (?NotificationSetting $record): bool => static::eventFor($record)?->locked() ?? false)
                        ->dehydrated(fn (?NotificationSetting $record): bool => ! (static::eventFor($record)?->locked() ?? false))
                        ->helperText(fn (?NotificationSetting $record): string => (static::eventFor($record)?->locked() ?? false)
                            ? 'Dikirim ke penerima sesuai alur (tidak dapat diubah).'
                            : 'Hanya user dengan role terpilih yang menerima email.'),
                ]),
        ]);
    }

    public static function eventFor(?NotificationSetting $record): ?NotificationEvent
    {
        if ($record === null) {
            return null;
        }

        return NotificationEvent::tryFrom($record->event_key);
    }
}
