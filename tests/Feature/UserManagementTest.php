<?php

namespace Tests\Feature;

use App\Actions\User\ActivateUser;
use App\Actions\User\DeactivateUser;
use App\Enums\AccountStatus;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $user->assignRole('super_admin');

        return $user;
    }

    // ---------- Akses halaman ----------

    public function test_super_admin_can_open_internal_users_page(): void
    {
        $this->actingAs($this->superAdmin())
            ->get('/admin/users')
            ->assertSuccessful();
    }

    public function test_non_super_admin_cannot_open_internal_users_page(): void
    {
        $hsse = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $hsse->assignRole('hsse');

        $this->actingAs($hsse)->get('/admin/users')->assertForbidden();
    }

    public function test_internal_list_excludes_transportir(): void
    {
        $admin = $this->superAdmin();

        $internal = User::factory()->create(['name' => 'Petugas HSE', 'account_status' => AccountStatus::Approved]);
        $internal->assignRole('hsse');

        $transportir = User::factory()->create(['name' => 'PIC Transportir', 'account_status' => AccountStatus::Approved]);
        $transportir->assignRole('transportir');

        $this->actingAs($admin);

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$admin, $internal])
            ->assertCanNotSeeTableRecords([$transportir]);
    }

    // ---------- Membuat akun internal ----------

    public function test_super_admin_can_create_internal_user(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Petugas FTM',
                'email' => 'ftm@okim.test',
                'password' => 'Rahasia123',
                'password_confirmation' => 'Rahasia123',
                'roles' => ['ftm'],
                'account_status' => AccountStatus::Approved->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'ftm@okim.test')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('ftm'));
        $this->assertSame(AccountStatus::Approved, $user->account_status);
        $this->assertTrue(Hash::check('Rahasia123', $user->password));
    }

    public function test_created_internal_user_can_access_admin_panel(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Petugas HSE',
                'email' => 'hse@okim.test',
                'password' => 'Rahasia123',
                'password_confirmation' => 'Rahasia123',
                'roles' => ['hsse'],
                'account_status' => AccountStatus::Approved->value,
            ])
            ->call('create');

        $user = User::where('email', 'hse@okim.test')->first();

        $this->actingAs($user)->get('/admin')->assertSuccessful();
    }

    public function test_can_create_multiple_internal_roles(): void
    {
        $this->actingAs($this->superAdmin());

        foreach (['qq' => 'qq@okim.test', 'distribusi' => 'dist@okim.test'] as $role => $email) {
            Livewire::test(CreateUser::class)
                ->fillForm([
                    'name' => 'Petugas '.strtoupper($role),
                    'email' => $email,
                    'password' => 'Rahasia123',
                    'password_confirmation' => 'Rahasia123',
                    'roles' => [$role],
                    'account_status' => AccountStatus::Approved->value,
                ])
                ->call('create')
                ->assertHasNoFormErrors();

            $this->assertTrue(User::where('email', $email)->first()->hasRole($role));
        }
    }

    public function test_creation_requires_at_least_one_role(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Tanpa Role',
                'email' => 'norole@okim.test',
                'password' => 'Rahasia123',
                'password_confirmation' => 'Rahasia123',
                'roles' => [],
            ])
            ->call('create')
            ->assertHasFormErrors(['roles']);
    }

    public function test_creation_requires_strong_password(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Lemah',
                'email' => 'lemah@okim.test',
                'password' => 'lemah',
                'password_confirmation' => 'lemah',
                'roles' => ['qq'],
            ])
            ->call('create')
            ->assertHasFormErrors(['password']);
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'ada@okim.test']);

        $this->actingAs($this->superAdmin());

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Duplikat',
                'email' => 'ada@okim.test',
                'password' => 'Rahasia123',
                'password_confirmation' => 'Rahasia123',
                'roles' => ['qq'],
            ])
            ->call('create')
            ->assertHasFormErrors(['email']);
    }

    // ---------- Edit ----------

    public function test_super_admin_can_change_role_and_status(): void
    {
        $target = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $target->assignRole('qq');

        $this->actingAs($this->superAdmin());

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm([
                'roles' => ['distribusi'],
                'account_status' => AccountStatus::Suspended->value,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $target->refresh();

        $this->assertTrue($target->hasRole('distribusi'));
        $this->assertFalse($target->hasRole('qq'));
        $this->assertSame(AccountStatus::Suspended, $target->account_status);
    }

    public function test_password_unchanged_when_left_blank_on_edit(): void
    {
        $target = User::factory()->create([
            'password' => 'PasswordLama123',
            'account_status' => AccountStatus::Approved,
        ]);
        $target->assignRole('qq');
        $originalHash = $target->password;

        $this->actingAs($this->superAdmin());

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm(['name' => 'Nama Diubah'])
            ->call('save')
            ->assertHasNoFormErrors();

        $target->refresh();

        $this->assertSame($originalHash, $target->password);
        $this->assertTrue(Hash::check('PasswordLama123', $target->password));
        $this->assertSame('Nama Diubah', $target->name);
    }

    public function test_password_changed_when_filled_on_edit(): void
    {
        $target = User::factory()->create([
            'password' => 'PasswordLama123',
            'account_status' => AccountStatus::Approved,
        ]);
        $target->assignRole('qq');

        $this->actingAs($this->superAdmin());

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm([
                'password' => 'PasswordBaru456',
                'password_confirmation' => 'PasswordBaru456',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('PasswordBaru456', $target->fresh()->password));
    }

    // ---------- Proteksi ----------

    public function test_cannot_deactivate_self(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin);

        Livewire::test(ListUsers::class)
            ->assertTableActionHidden('deactivate', $admin);
    }

    public function test_cannot_deactivate_last_active_super_admin(): void
    {
        // Hanya ada satu super admin yang aktif.
        $lastActive = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $lastActive->assignRole('super_admin');

        // Aktor adalah super admin lain yang sudah nonaktif (agar hitungan aktif tetap satu).
        $actor = User::factory()->create(['account_status' => AccountStatus::Suspended]);
        $actor->assignRole('super_admin');

        $this->expectException(ValidationException::class);
        app(DeactivateUser::class)->execute($lastActive, $actor);
    }

    public function test_can_deactivate_super_admin_when_another_active_exists(): void
    {
        $admin = $this->superAdmin();

        $other = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $other->assignRole('super_admin');

        app(DeactivateUser::class)->execute($other, $admin);

        $this->assertSame(AccountStatus::Suspended, $other->fresh()->account_status);
    }

    public function test_can_deactivate_non_super_admin_internal_user(): void
    {
        $admin = $this->superAdmin();

        $target = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $target->assignRole('qq');

        app(DeactivateUser::class)->execute($target, $admin);

        $this->assertSame(AccountStatus::Suspended, $target->fresh()->account_status);
    }

    public function test_deactivated_user_cannot_login_to_panel(): void
    {
        $admin = $this->superAdmin();

        $target = User::factory()->create([
            'email' => 'qq@okim.test',
            'password' => 'Rahasia123',
            'account_status' => AccountStatus::Approved,
        ]);
        $target->assignRole('qq');

        app(DeactivateUser::class)->execute($target, $admin);

        $this->actingAs($target->fresh())->get('/admin')->assertForbidden();
    }

    public function test_activate_restores_access(): void
    {
        $admin = $this->superAdmin();

        $target = User::factory()->create(['account_status' => AccountStatus::Suspended]);
        $target->assignRole('qq');

        app(ActivateUser::class)->execute($target);

        $this->assertSame(AccountStatus::Approved, $target->fresh()->account_status);

        $this->actingAs($target->fresh())->get('/admin')->assertSuccessful();
    }

    // ---------- Verifikasi transportir tetap terpisah ----------

    public function test_transporter_verification_page_still_works(): void
    {
        $admin = $this->superAdmin();

        $transportir = User::factory()->create(['account_status' => AccountStatus::Pending]);
        $transportir->assignRole('transportir');

        $this->actingAs($admin)
            ->get('/admin/transporters')
            ->assertSuccessful();

        $this->actingAs($admin)
            ->get('/admin/transporters/'.$transportir->getRouteKey())
            ->assertSuccessful();
    }

    public function test_hsse_cannot_open_transporter_verification(): void
    {
        $hsse = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $hsse->assignRole('hsse');

        $this->actingAs($hsse)->get('/admin/transporters')->assertForbidden();
    }
}
