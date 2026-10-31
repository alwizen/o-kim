<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\KimApplication;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Akun berstatus Pending tidak boleh menyentuh data operasional lewat rute web,
 * meskipun role-nya memiliki permission (mis. TAD yang mendapat access_admin_panel).
 */
class PendingAccountAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function internalWithStatus(AccountStatus $status, string $role = 'hsse'): User
    {
        $user = User::factory()->create(['account_status' => $status]);
        $user->assignRole($role);

        return $user;
    }

    private function applicationForOtherCompany(): KimApplication
    {
        return KimApplication::factory()->create();
    }

    public function test_pending_internal_cannot_download_inspection_report_of_other_company(): void
    {
        $this->actingAs($this->internalWithStatus(AccountStatus::Pending))
            ->get(route('inspection-report.download', $this->applicationForOtherCompany()))
            ->assertForbidden();
    }

    public function test_pending_internal_cannot_preview_inspection_report_of_other_company(): void
    {
        $this->actingAs($this->internalWithStatus(AccountStatus::Pending))
            ->get(route('inspection-report.preview', $this->applicationForOtherCompany()))
            ->assertForbidden();
    }

    public function test_pending_internal_cannot_download_finding_report_of_other_company(): void
    {
        $this->actingAs($this->internalWithStatus(AccountStatus::Pending))
            ->get(route('berita-acara.download', $this->applicationForOtherCompany()))
            ->assertForbidden();
    }

    public function test_pending_internal_cannot_preview_finding_report_of_other_company(): void
    {
        $this->actingAs($this->internalWithStatus(AccountStatus::Pending))
            ->get(route('berita-acara.preview', $this->applicationForOtherCompany()))
            ->assertForbidden();
    }

    public function test_pending_internal_can_still_open_waiting_page(): void
    {
        $this->actingAs($this->internalWithStatus(AccountStatus::Pending))
            ->get(route('registration.pending.internal'))
            ->assertSuccessful();
    }

    public function test_pending_internal_can_still_logout(): void
    {
        $this->actingAs($this->internalWithStatus(AccountStatus::Pending))
            ->post(route('logout'))
            ->assertRedirect('/');
    }

    public function test_suspended_internal_is_blocked_from_operational_routes(): void
    {
        $this->actingAs($this->internalWithStatus(AccountStatus::Suspended))
            ->get(route('inspection-report.download', $this->applicationForOtherCompany()))
            ->assertForbidden();
    }

    public function test_approved_internal_passes_the_status_guard(): void
    {
        // Approved TAD harus lolos guard status; yang penting bukan 403.
        $this->actingAs($this->internalWithStatus(AccountStatus::Approved))
            ->get(route('inspection-report.download', $this->applicationForOtherCompany()))
            ->assertSuccessful();
    }

    public function test_pending_transporter_keeps_profile_access(): void
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Pending]);
        $user->assignRole('transportir');

        $this->actingAs($user)
            ->get(route('registration.profile.edit'))
            ->assertSuccessful();
    }
}
