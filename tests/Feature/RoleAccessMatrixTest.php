<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\Department;
use App\Filament\Pages\ManageSettings;
use App\Filament\Resources\DocumentTypes\DocumentTypeResource;
use App\Filament\Resources\Findings\FindingResource;
use App\Filament\Resources\InspectionChecklists\InspectionChecklistResource;
use App\Filament\Resources\KimApplications\KimApplicationResource;
use App\Filament\Resources\KimCards\KimCardResource;
use App\Filament\Resources\Transporters\TransporterResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\Vehicles\VehicleResource;
use App\Models\KimApplication;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RoleAccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * Matriks harapan: role => [resource => boleh?]
     *
     * @return array<string, array<string, bool>>
     */
    private function matrix(): array
    {
        return [
            'super_admin' => [
                UserResource::class => true,
                TransporterResource::class => true,
                KimCardResource::class => true,
                KimApplicationResource::class => true,
                FindingResource::class => true,
                InspectionChecklistResource::class => true,
                DocumentTypeResource::class => true,
                VehicleResource::class => true,
            ],
            'admin_verifikasi' => [
                UserResource::class => false,
                TransporterResource::class => true,
                KimCardResource::class => false,
                KimApplicationResource::class => false,
                FindingResource::class => false,
                InspectionChecklistResource::class => false,
                DocumentTypeResource::class => false,
                VehicleResource::class => true,
            ],
            'hsse' => [
                UserResource::class => false,
                TransporterResource::class => false,
                KimCardResource::class => false,
                KimApplicationResource::class => true,
                FindingResource::class => true,
                InspectionChecklistResource::class => false,
                DocumentTypeResource::class => false,
                VehicleResource::class => true,
            ],
            'distribusi' => [
                UserResource::class => false,
                TransporterResource::class => false,
                KimCardResource::class => false,
                KimApplicationResource::class => true,
                FindingResource::class => true,
                InspectionChecklistResource::class => false,
                DocumentTypeResource::class => false,
                VehicleResource::class => true,
            ],
            'qq' => [
                UserResource::class => false,
                TransporterResource::class => false,
                KimCardResource::class => false,
                KimApplicationResource::class => true,
                FindingResource::class => true,
                InspectionChecklistResource::class => false,
                DocumentTypeResource::class => false,
                VehicleResource::class => true,
            ],
            'ftm' => [
                UserResource::class => false,
                TransporterResource::class => false,
                KimCardResource::class => true,
                KimApplicationResource::class => true,
                FindingResource::class => true,
                InspectionChecklistResource::class => false,
                DocumentTypeResource::class => false,
                VehicleResource::class => true,
            ],
            'transportir' => [
                UserResource::class => false,
                TransporterResource::class => false,
                KimCardResource::class => false,
                KimApplicationResource::class => false,
                FindingResource::class => false,
                InspectionChecklistResource::class => false,
                DocumentTypeResource::class => false,
                VehicleResource::class => false,
            ],
        ];
    }

    public function test_resource_access_matrix_matches_expectation(): void
    {
        foreach ($this->matrix() as $role => $resources) {
            foreach ($resources as $resource => $expected) {
                $user = $this->userWithRole($role);
                $this->actingAs($user);

                $this->assertSame(
                    $expected,
                    $resource::canAccess(),
                    "Role [{$role}] akses [{$resource}] diharapkan ".($expected ? 'BOLEH' : 'TIDAK BOLEH').'.'
                );

                auth()->logout();
            }
        }
    }

    public function test_settings_page_access_matrix(): void
    {
        foreach (['super_admin' => true, 'admin_verifikasi' => false, 'hsse' => false, 'ftm' => false] as $role => $expected) {
            $this->actingAs($this->userWithRole($role));

            $this->assertSame(
                $expected,
                ManageSettings::canAccess(),
                "Role [{$role}] akses Pengaturan Sistem."
            );

            auth()->logout();
        }
    }

    public function test_direct_url_is_blocked_for_unauthorized_role(): void
    {
        $ftm = $this->userWithRole('ftm');

        $this->actingAs($ftm)->get('/admin/users')->assertForbidden();
        $this->actingAs($ftm)->get('/admin/users/create')->assertForbidden();
        $this->actingAs($ftm)->get('/admin/inspection-checklists')->assertForbidden();
        $this->actingAs($ftm)->get('/admin/document-types')->assertForbidden();
        $this->actingAs($ftm)->get('/admin/manage-settings')->assertForbidden();

        // FTM berwenang memverifikasi dokumen, sehingga daftar kendaraan dapat diakses.
        $this->actingAs($ftm)->get('/admin/vehicles')->assertSuccessful();
    }

    public function test_hsse_cannot_access_transporter_verification_directly(): void
    {
        $hsse = $this->userWithRole('hsse');

        $this->actingAs($hsse)->get('/admin/transporters')->assertForbidden();
    }

    public function test_admin_verifikasi_can_access_transporter_verification(): void
    {
        $admin = $this->userWithRole('admin_verifikasi');

        $this->actingAs($admin)->get('/admin/transporters')->assertSuccessful();
    }

    public function test_ftm_can_view_findings_but_cannot_manage_checklists(): void
    {
        $ftm = $this->userWithRole('ftm');

        $this->assertTrue($ftm->can('view_findings'));
        $this->assertFalse($ftm->can('manage_checklists'));

        $this->actingAs($ftm)->get('/admin/findings')->assertSuccessful();
        $this->actingAs($ftm)->get('/admin/inspection-checklists')->assertForbidden();
    }

    public function test_inspection_officers_can_view_applications(): void
    {
        foreach (['hsse', 'distribusi', 'qq', 'ftm'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get('/admin/kim-applications')->assertSuccessful();

            auth()->logout();
        }
    }

    public function test_user_policy_delete_is_always_denied(): void
    {
        $admin = $this->userWithRole('super_admin');
        $target = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $target->assignRole('qq');

        $this->assertFalse(Gate::forUser($admin)->allows('delete', $target));
        $this->assertFalse(Gate::forUser($admin)->allows('deleteAny', User::class));
    }

    public function test_user_policy_scopes_internal_vs_transportir(): void
    {
        $admin = $this->userWithRole('super_admin');
        $transportir = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $transportir->assignRole('transportir');

        $this->assertFalse(Gate::forUser($admin)->allows('view', $transportir));

        $this->actingAs($admin);
        $this->assertTrue(UserResource::canAccess());
    }

    public function test_kim_application_policy_inspect_is_per_department(): void
    {
        $hsse = $this->userWithRole('hsse');
        $application = KimApplication::factory()->create();

        $this->assertTrue(Gate::forUser($hsse)->allows('inspect', [$application, Department::Hsse]));
        $this->assertFalse(Gate::forUser($hsse)->allows('inspect', [$application, Department::Distribusi]));
        $this->assertFalse(Gate::forUser($hsse)->allows('inspect', [$application, Department::Qq]));
    }

    public function test_kim_application_policy_approve_is_per_department(): void
    {
        $ftm = $this->userWithRole('ftm');
        $application = KimApplication::factory()->create();

        $this->assertTrue(Gate::forUser($ftm)->allows('approve', [$application, Department::Ftm]));
        $this->assertFalse(Gate::forUser($ftm)->allows('approve', [$application, Department::Hsse]));

        $hsse = $this->userWithRole('hsse');
        $this->assertTrue(Gate::forUser($hsse)->allows('approve', [$application, Department::Hsse]));
        $this->assertFalse(Gate::forUser($hsse)->allows('approve', [$application, Department::Ftm]));
    }
}
