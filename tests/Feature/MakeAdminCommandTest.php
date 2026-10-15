<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MakeAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_creates_new_admin_with_approved_status_and_role(): void
    {
        $this->artisan('okim:make-admin', [
            'email' => 'admin@okim.test',
            '--name' => 'Super Admin',
            '--role' => 'super_admin',
            '--password' => 'password2#',
        ])->assertSuccessful();

        $user = User::where('email', 'admin@okim.test')->first();

        $this->assertNotNull($user);
        $this->assertSame('Super Admin', $user->name);
        $this->assertSame(AccountStatus::Approved, $user->account_status);
        $this->assertTrue($user->hasRole('super_admin'));
        $this->assertTrue(Hash::check('password2#', $user->password));
    }

    public function test_created_admin_can_access_admin_panel(): void
    {
        $this->artisan('okim:make-admin', [
            'email' => 'admin@okim.test',
            '--password' => 'password2#',
        ])->assertSuccessful();

        $user = User::where('email', 'admin@okim.test')->first();

        $this->actingAs($user)->get('/admin')->assertSuccessful();
        $this->assertTrue($user->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_name_defaults_to_email_prefix(): void
    {
        $this->artisan('okim:make-admin', [
            'email' => 'budi@okim.test',
            '--password' => 'password2#',
        ])->assertSuccessful();

        $this->assertSame('budi', User::where('email', 'budi@okim.test')->first()->name);
    }

    public function test_password_is_generated_and_hashed_when_not_provided(): void
    {
        $this->artisan('okim:make-admin', ['email' => 'admin@okim.test'])
            ->assertSuccessful();

        $user = User::where('email', 'admin@okim.test')->first();

        $this->assertNotNull($user->password);
        $this->assertNotSame('admin', $user->password);
        $this->assertTrue(str_starts_with($user->password, '$2y$') || str_starts_with($user->password, '$argon2'));
    }

    public function test_is_idempotent(): void
    {
        $this->artisan('okim:make-admin', [
            'email' => 'admin@okim.test',
            '--password' => 'password2#',
        ])->assertSuccessful();

        $this->artisan('okim:make-admin', [
            'email' => 'admin@okim.test',
            '--password' => 'password2#',
        ])->assertSuccessful();

        $this->assertSame(1, User::where('email', 'admin@okim.test')->count());
    }

    public function test_existing_password_is_untouched_without_password_option(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@okim.test',
            'password' => 'password-lama-123',
            'account_status' => AccountStatus::Pending,
        ]);
        $originalHash = $user->password;

        $this->artisan('okim:make-admin', ['email' => 'admin@okim.test'])
            ->assertSuccessful();

        $user->refresh();

        $this->assertSame($originalHash, $user->password);
        $this->assertTrue(Hash::check('password-lama-123', $user->password));
        $this->assertSame(AccountStatus::Approved, $user->account_status);
    }

    public function test_password_option_overrides_existing_password(): void
    {
        User::factory()->create([
            'email' => 'admin@okim.test',
            'password' => 'password-lama-123',
        ]);

        $this->artisan('okim:make-admin', [
            'email' => 'admin@okim.test',
            '--password' => 'password-baru-456',
        ])->assertSuccessful();

        $user = User::where('email', 'admin@okim.test')->first();

        $this->assertTrue(Hash::check('password-baru-456', $user->password));
    }

    public function test_approves_previously_pending_account(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@okim.test',
            'account_status' => AccountStatus::Pending,
            'rejection_reason' => 'Sebelumnya ditolak',
        ]);

        $this->artisan('okim:make-admin', [
            'email' => 'admin@okim.test',
            '--password' => 'password2#',
        ])->assertSuccessful();

        $user->refresh();

        $this->assertSame(AccountStatus::Approved, $user->account_status);
        $this->assertNull($user->rejection_reason);
    }

    public function test_rejects_invalid_email(): void
    {
        $this->artisan('okim:make-admin', ['email' => 'bukan-email'])
            ->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_rejects_short_password(): void
    {
        $this->artisan('okim:make-admin', [
            'email' => 'admin@okim.test',
            '--password' => 'pendek',
        ])->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_rejects_non_internal_role(): void
    {
        $this->artisan('okim:make-admin', [
            'email' => 'admin@okim.test',
            '--role' => 'transportir',
            '--password' => 'password2#',
        ])->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_rejects_unknown_role(): void
    {
        $this->artisan('okim:make-admin', [
            'email' => 'admin@okim.test',
            '--role' => 'role_tidak_ada',
            '--password' => 'password2#',
        ])->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_fails_clearly_when_roles_not_seeded(): void
    {
        // Hapus role yang sudah di-seed untuk mensimulasikan database baru tanpa seeder.
        Role::query()->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->artisan('okim:make-admin', [
            'email' => 'admin@okim.test',
            '--password' => 'password2#',
        ])->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_can_assign_other_internal_roles(): void
    {
        $this->artisan('okim:make-admin', [
            'email' => 'ftm@okim.test',
            '--role' => 'ftm',
            '--password' => 'password2#',
        ])->assertSuccessful();

        $user = User::where('email', 'ftm@okim.test')->first();

        $this->assertTrue($user->hasRole('ftm'));
        $this->assertFalse($user->hasRole('super_admin'));
    }
}
