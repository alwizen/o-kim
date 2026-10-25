<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\InternalRole;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * SEC-02: hanya super_admin yang boleh memberikan role super_admin
 * dan permission sensitif.
 */
class UserPrivilegeEscalationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $user->assignRole($role);

        return $user;
    }

    // ---------- Opsi role ----------

    public function test_non_super_admin_does_not_see_super_admin_role_option(): void
    {
        $this->actingAs($this->makeUser('admin_verifikasi'));

        $this->assertArrayNotHasKey(InternalRole::SuperAdmin->value, UserForm::roleOptions());
        $this->assertArrayHasKey(InternalRole::Hsse->value, UserForm::roleOptions());
    }

    public function test_super_admin_sees_super_admin_role_option(): void
    {
        $this->actingAs($this->makeUser('super_admin'));

        $this->assertArrayHasKey(InternalRole::SuperAdmin->value, UserForm::roleOptions());
    }

    // ---------- Edit user ----------

    public function test_non_super_admin_cannot_edit_super_admin_account(): void
    {
        $actor = $this->makeUser('admin_verifikasi');
        $target = $this->makeUser('super_admin');

        $this->assertFalse($actor->can('update', $target));
    }

    public function test_super_admin_can_edit_super_admin_account(): void
    {
        $actor = $this->makeUser('super_admin');
        $target = $this->makeUser('super_admin');

        $this->assertTrue($actor->can('update', $target));
    }

    public function test_super_admin_role_is_not_an_allowed_option_for_non_super_admin(): void
    {
        $actor = $this->makeUser('admin_verifikasi');
        $target = $this->makeUser('hsse');

        $this->actingAs($actor);

        // Opsi form tidak memuat super_admin, sehingga payload manipulasi
        // (jika dikirim) akan tersaring oleh filter allowed di saveRelationshipsUsing.
        $allowed = array_keys(UserForm::roleOptions());

        $this->assertNotContains(InternalRole::SuperAdmin->value, $allowed);
        $this->assertFalse($target->fresh()->hasRole(InternalRole::SuperAdmin->value));
    }

    // ---------- Edit role ----------

    public function test_non_super_admin_cannot_edit_super_admin_role(): void
    {
        // Pemegang manage_roles tetapi bukan super_admin.
        $roleManager = $this->makeUser('hsse');
        $roleManager->givePermissionTo('manage_roles');
        $this->actingAs($roleManager);

        $superAdminRole = Role::findByName(InternalRole::SuperAdmin->value, 'web');
        $hsseRole = Role::findByName(InternalRole::Hsse->value, 'web');

        $this->assertTrue($roleManager->can('manage_roles'));
        $this->assertFalse(RoleResource::canEdit($superAdminRole));
        $this->assertTrue(RoleResource::canEdit($hsseRole));
    }

    public function test_super_admin_can_edit_super_admin_role(): void
    {
        $this->actingAs($this->makeUser('super_admin'));

        $superAdminRole = Role::findByName(InternalRole::SuperAdmin->value, 'web');

        $this->assertTrue(RoleResource::canEdit($superAdminRole));
    }
}
