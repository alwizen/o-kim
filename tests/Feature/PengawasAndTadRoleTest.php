<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApplicationStatus;
use App\Enums\Department;
use App\Enums\FindingStatus;
use App\Enums\InspectionResultValue;
use App\Models\Finding;
use App\Models\FindingAction;
use App\Models\KimApplication;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PengawasAndTadRoleTest extends TestCase
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

    // ---------- Role baru tersedia ----------

    public function test_pengawas_roles_are_seeded(): void
    {
        foreach (['pengawas_hsse', 'pengawas_qq', 'pengawas_distribusi'] as $role) {
            $this->assertDatabaseHas('roles', ['name' => $role, 'guard_name' => 'web']);
        }
    }

    // ---------- TAD: hanya checklist ----------

    public function test_tad_can_fill_checklist_but_cannot_approve(): void
    {
        $tad = $this->userWithRole('hsse');

        $this->assertTrue($tad->can('inspect_hsse'));
        $this->assertTrue($tad->can('view_applications'));

        // TAD tidak lagi bisa approval per-department.
        $this->assertFalse($tad->can('approve_hsse'));
        $this->assertFalse($tad->can('issue_kim'));
        $this->assertFalse($tad->can('schedule_inspection'));
    }

    public function test_tad_is_identified_as_tad(): void
    {
        foreach (['hsse', 'qq', 'distribusi'] as $role) {
            $this->assertTrue($this->userWithRole($role)->isTad());
        }

        foreach (['super_admin', 'admin_verifikasi', 'ftm', 'pengawas_hsse', 'pengawas_qq', 'pengawas_distribusi'] as $role) {
            $this->assertFalse($this->userWithRole($role)->isTad());
        }
    }

    // ---------- Pengawas: lihat semua, approval temuan TAD ----------

    public function test_pengawas_can_view_applications_and_findings(): void
    {
        $pengawas = $this->userWithRole('pengawas_hsse');

        $this->assertTrue($pengawas->can('view_applications'));
        $this->assertTrue($pengawas->can('view_findings'));
        $this->assertTrue($pengawas->can('view_kim_cards'));
    }

    public function test_pengawas_can_approve_own_department_and_issue_kim(): void
    {
        $pengawasHsse = $this->userWithRole('pengawas_hsse');

        $this->assertTrue($pengawasHsse->can('approve_hsse'));
        $this->assertFalse($pengawasHsse->can('approve_qq'));
        $this->assertFalse($pengawasHsse->can('approve_distribusi'));

        // Semua pengawas dapat menerbitkan KIM.
        $this->assertTrue($pengawasHsse->can('issue_kim'));
        $this->assertTrue($this->userWithRole('pengawas_qq')->can('issue_kim'));
        $this->assertTrue($this->userWithRole('pengawas_distribusi')->can('issue_kim'));
    }

    public function test_pengawas_cannot_fill_checklist(): void
    {
        foreach (['pengawas_hsse', 'pengawas_qq', 'pengawas_distribusi'] as $role) {
            $pengawas = $this->userWithRole($role);
            $this->assertFalse($pengawas->can('inspect_hsse'));
            $this->assertFalse($pengawas->can('inspect_qq'));
            $this->assertFalse($pengawas->can('inspect_distribusi'));
        }
    }

    // ---------- Pengawas hanya bisa verifikasi temuan dari TAD ----------

    private function makeFinding(User $creator): Finding
    {
        $finding = Finding::create([
            'kim_application_id' => KimApplication::factory()->create()->id,
            'department' => Department::Hsse,
            'description' => 'Temuan uji',
            'status' => FindingStatus::SubmittedForVerification,
            'created_by' => $creator->id,
        ]);

        FindingAction::create([
            'finding_id' => $finding->id,
            'submitted_by' => $creator->id,
            'description' => 'Tindak lanjut uji',
        ]);

        return $finding;
    }

    public function test_pengawas_can_verify_finding_from_tad(): void
    {
        $tad = $this->userWithRole('hsse');
        $pengawas = $this->userWithRole('pengawas_hsse');

        $finding = $this->makeFinding($tad);

        $this->assertTrue($pengawas->canVerifyFinding($finding));
    }

    public function test_pengawas_cannot_verify_finding_from_non_tad(): void
    {
        $nonTad = $this->userWithRole('ftm');
        $pengawas = $this->userWithRole('pengawas_hsse');

        $finding = $this->makeFinding($nonTad);

        $this->assertFalse($pengawas->canVerifyFinding($finding));
    }

    public function test_tad_can_verify_own_finding_but_not_others(): void
    {
        $tad = $this->userWithRole('hsse');
        $otherTad = $this->userWithRole('hsse');

        $own = $this->makeFinding($tad);
        $others = $this->makeFinding($otherTad);

        $this->assertTrue($tad->canVerifyFinding($own));
        $this->assertFalse($tad->canVerifyFinding($others));
    }

    // ---------- Admin verifikasi: schedule, checklist, dokumen, KIM ----------

    public function test_admin_verifikasi_can_schedule_manage_checklist_and_issue_kim(): void
    {
        $adminVerifikasi = $this->userWithRole('admin_verifikasi');

        $this->assertTrue($adminVerifikasi->can('schedule_inspection'));
        $this->assertTrue($adminVerifikasi->can('manage_checklists'));
        $this->assertTrue($adminVerifikasi->can('verify_documents'));
        $this->assertTrue($adminVerifikasi->can('issue_kim'));
        $this->assertTrue($adminVerifikasi->can('view_applications'));
        $this->assertTrue($adminVerifikasi->can('view_kim_cards'));
        $this->assertTrue($adminVerifikasi->can('view_findings'));
    }

    public function test_admin_verifikasi_cannot_approve_or_manage_users(): void
    {
        $adminVerifikasi = $this->userWithRole('admin_verifikasi');

        $this->assertFalse($adminVerifikasi->can('approve_hsse'));
        $this->assertFalse($adminVerifikasi->can('approve_qq'));
        $this->assertFalse($adminVerifikasi->can('approve_final'));
        $this->assertFalse($adminVerifikasi->can('manage_users'));
        $this->assertFalse($adminVerifikasi->can('inspect_hsse'));
    }

    // ---------- Penerbit KIM ----------

    public function test_only_ftm_admin_verifikasi_super_admin_and_pengawas_can_issue_kim(): void
    {
        $issuers = ['ftm', 'admin_verifikasi', 'super_admin', 'pengawas_hsse', 'pengawas_qq', 'pengawas_distribusi'];
        foreach ($issuers as $role) {
            $this->assertTrue($this->userWithRole($role)->can('issue_kim'), $role);
        }

        $nonIssuers = ['hsse', 'qq', 'distribusi', 'transportir'];
        foreach ($nonIssuers as $role) {
            $this->assertFalse($this->userWithRole($role)->can('issue_kim'), $role);
        }
    }

    // ---------- Pengawas punya gate approve QQ meski aplikasi null (permission-only) ----------

    public function test_pengawas_permission_only_gate(): void
    {
        $tad = $this->userWithRole('qq');
        $this->assertSame(InspectionResultValue::Compliant->value, 'compliant');
        $this->assertSame(ApplicationStatus::Submitted->value, 'submitted');

        // Pengawas QQ memang punya permission approval QQ.
        $pengawas = $this->userWithRole('pengawas_qq');
        $this->assertTrue($pengawas->can('approve_qq'));
    }
}
