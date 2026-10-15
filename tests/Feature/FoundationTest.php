<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_permission_seeder_is_idempotent(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->assertSame(7, Role::count());
        $this->assertSame(21, Permission::count());
    }

    public function test_all_required_roles_are_seeded(): void
    {
        $this->seed(RolePermissionSeeder::class);

        foreach (['super_admin', 'admin_verifikasi', 'hsse', 'distribusi', 'qq', 'ftm', 'transportir'] as $role) {
            $this->assertDatabaseHas('roles', ['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_new_user_defaults_to_pending_account_status(): void
    {
        $user = User::factory()->create();

        $this->assertSame(AccountStatus::Pending, $user->account_status);
    }

    public function test_pending_user_cannot_access_any_panel(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create(['account_status' => AccountStatus::Pending]);
        $user->assignRole('transportir');

        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));
        $this->assertFalse($user->canAccessPanel(Filament::getPanel('transportir')));
    }

    public function test_transportir_role_cannot_access_admin_panel(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $user->assignRole('transportir');

        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));
        $this->assertTrue($user->canAccessPanel(Filament::getPanel('transportir')));
    }

    public function test_internal_staff_cannot_access_transportir_panel(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $user->assignRole('hsse');

        $this->assertTrue($user->canAccessPanel(Filament::getPanel('admin')));
        $this->assertFalse($user->canAccessPanel(Filament::getPanel('transportir')));
    }

    public function test_suspended_user_loses_panel_access(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $user->assignRole('transportir');

        $user->update(['account_status' => AccountStatus::Suspended]);

        $this->assertFalse($user->fresh()->canAccessPanel(Filament::getPanel('transportir')));
    }

    public function test_admin_panel_login_page_is_reachable(): void
    {
        $this->get('/admin/login')->assertSuccessful();
    }

    public function test_transportir_panel_requires_authentication(): void
    {
        $this->get('/transportir')->assertRedirect();
    }
}
