<?php

namespace App\Filament\Pages;

use App\Enums\AccountStatus;
use App\Models\User;
use BackedEnum;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use UnitEnum;

/**
 * Profil pengguna internal: ubah nama, email, dan kata sandi sendiri.
 *
 * Hanya didaftarkan di panel admin. Akun transportir memakai CompanyProfile
 * pada panelnya sendiri.
 */
class MyProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?string $navigationLabel = 'Profil Saya';

    protected static ?string $title = 'Profil Saya';

    protected static string|UnitEnum|null $navigationGroup = 'Data & Profil';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.pages.my-profile';

    public ?array $data = [];

    /**
     * Hanya untuk pengguna internal yang sudah disetujui.
     * Akun pending sudah diblokir User::canAccessPanel(); pemeriksaan status
     * diulang di sini agar halaman ini tetap aman bila aturan panel berubah.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->account_status === AccountStatus::Approved
            && ! $user->hasRole('transportir')
            && $user->can('access_admin_panel');
    }

    public function mount(): void
    {
        $user = $this->getUser();

        $this->form->fill([
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Data Akun')
                    ->description('Nama dan email yang dipakai untuk masuk ke panel.')
                    ->icon(Heroicon::OutlinedUser)
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(
                                table: User::class,
                                column: 'email',
                                ignorable: fn (): User => $this->getUser(),
                            )
                            ->helperText('Email ini dipakai untuk masuk dan menerima notifikasi.'),
                    ]),

                Section::make('Ubah Kata Sandi')
                    ->description('Biarkan kosong bila tidak ingin mengubah kata sandi.')
                    ->icon(Heroicon::OutlinedKey)
                    ->columns(3)
                    ->schema([
                        TextInput::make('current_password')
                            ->label('Kata Sandi Saat Ini')
                            ->password()
                            ->revealable()
                            ->dehydrated(false)
                            ->requiredWith('password')
                            ->rules([
                                fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    if (blank($value)) {
                                        return;
                                    }

                                    if (! Hash::check((string) $value, $this->getUser()->password ?? '')) {
                                        $fail('Kata sandi saat ini tidak sesuai.');
                                    }
                                },
                            ]),

                        TextInput::make('password')
                            ->label('Kata Sandi Baru')
                            ->password()
                            ->revealable()
                            ->rules([Password::min(8)->letters()->mixedCase()->numbers()])
                            ->helperText('Minimal 8 karakter, kombinasi huruf besar, huruf kecil, dan angka.'),

                        TextInput::make('password_confirmation')
                            ->label('Ulangi Kata Sandi Baru')
                            ->password()
                            ->revealable()
                            ->requiredWith('password')
                            ->same('password'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $user = $this->getUser();
        $data = $this->form->getState();

        $newPassword = filled($data['password'] ?? null) ? (string) $data['password'] : null;
        $emailChanged = strcasecmp((string) $data['email'], (string) $user->email) !== 0;

        $user->name = $data['name'];
        $user->email = $data['email'];

        if ($emailChanged) {
            // Email baru belum terbukti milik pengguna.
            $user->email_verified_at = null;
        }

        if ($newPassword !== null) {
            $user->password = $newPassword; // di-hash oleh cast 'password' => 'hashed'
        }

        $user->save();

        if ($newPassword !== null) {
            // Sesi lain memakai kata sandi lama; minta login ulang di perangkat lain.
            $user->setRememberToken(str()->random(60));
            $user->save();
        }

        $this->form->fill([
            'name' => $user->name,
            'email' => $user->email,
            'current_password' => null,
            'password' => null,
            'password_confirmation' => null,
        ]);

        Notification::make()
            ->title('Profil diperbarui')
            ->body($newPassword !== null ? 'Kata sandi juga telah diubah.' : null)
            ->success()
            ->send();
    }

    private function getUser(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
