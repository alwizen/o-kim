<?php

namespace App\Console\Commands;

use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class MakeAdminCommand extends Command
{
    protected $signature = 'okim:make-admin
                            {email : Alamat email akun}
                            {--name= : Nama pengguna (default: bagian sebelum @)}
                            {--role=super_admin : Role internal yang diberikan}
                            {--password= : Password akun (minimal 8 karakter)}';

    protected $description = 'Membuat atau memperbarui akun administrator internal O-KIM (status disetujui + role)';

    /**
     * Role internal yang boleh diberikan melalui command ini.
     */
    private const INTERNAL_ROLES = [
        'super_admin',
        'admin_verifikasi',
        'hsse',
        'distribusi',
        'qq',
        'ftm',
    ];

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $roleName = (string) $this->option('role');
        $password = $this->option('password');
        $name = $this->option('name') ?: Str::before($email, '@');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->components->error('Alamat email tidak valid: '.$email);

            return self::FAILURE;
        }

        if (! in_array($roleName, self::INTERNAL_ROLES, true)) {
            $this->components->error(
                "Role '{$roleName}' bukan role internal yang diizinkan."
            );
            $this->components->info('Role yang tersedia: '.implode(', ', self::INTERNAL_ROLES));

            return self::FAILURE;
        }

        if (! Role::where('name', $roleName)->exists()) {
            $this->components->error("Role '{$roleName}' tidak ditemukan di database.");
            $this->components->info('Jalankan seeder terlebih dahulu: php artisan db:seed --class=RolePermissionSeeder');

            return self::FAILURE;
        }

        if ($password !== null && strlen((string) $password) < 8) {
            $this->components->error('Password minimal 8 karakter.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();
        $generatedPassword = null;

        if (! $user) {
            if ($password === null) {
                $password = Str::password(16);
                $generatedPassword = $password;
            }

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'account_status' => AccountStatus::Approved,
            ]);

            $this->components->info("Akun baru dibuat: {$email}");
        } else {
            $attributes = [
                'account_status' => AccountStatus::Approved,
                'rejection_reason' => null,
            ];

            if ($this->option('name')) {
                $attributes['name'] = $name;
            }

            // Password hanya diubah bila diberikan secara eksplisit.
            if ($password !== null) {
                $attributes['password'] = $password;
            }

            $user->update($attributes);

            $this->components->info("Akun diperbarui: {$email}");
        }

        $user->syncRoles([$roleName]);
        $user->unsetRelation('roles');

        $this->newLine();
        $this->table(
            ['Field', 'Nilai'],
            [
                ['Nama', $user->name],
                ['Email', $user->email],
                ['Role', $roleName],
                ['Status Akun', $user->account_status->label()],
                ['Password', $generatedPassword ? $generatedPassword.' (baru digenerate, catat sekarang)' : ($password ? 'diatur sesuai --password' : 'tidak diubah')],
            ]
        );

        $this->components->info('Akun dapat digunakan untuk login di '.url('/admin/login'));

        return self::SUCCESS;
    }
}
