<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\Transporter;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function approvedTransportir(): User
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $user->assignRole('transportir');
        $user->transporters()->attach(Transporter::factory()->create());

        return $user;
    }

    public function test_approved_transportir_visiting_login_is_redirected_to_panel(): void
    {
        $this->actingAs($this->approvedTransportir())
            ->get('/masuk')
            ->assertRedirect('/transportir');
    }

    public function test_approved_transportir_visiting_register_is_redirected_to_panel(): void
    {
        $this->actingAs($this->approvedTransportir())
            ->get('/daftar')
            ->assertRedirect('/transportir');
    }

    public function test_pending_user_visiting_login_is_redirected_to_profile_form(): void
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Pending]);
        $user->assignRole('transportir');

        $this->actingAs($user)
            ->get('/masuk')
            ->assertRedirect(route('registration.profile.edit'));
    }

    public function test_approved_admin_visiting_login_is_redirected_to_admin_panel(): void
    {
        $admin = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $admin->assignRole('super_admin');

        $this->actingAs($admin)
            ->get('/masuk')
            ->assertRedirect('/admin');
    }

    public function test_landing_shows_panel_button_when_approved_user_logged_in(): void
    {
        $this->actingAs($this->approvedTransportir())
            ->get('/')
            ->assertSuccessful()
            ->assertSee('Buka Panel')
            ->assertDontSee('Masuk ke Akun');
    }

    public function test_landing_shows_continue_registration_for_pending_user(): void
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Pending]);
        $user->assignRole('transportir');

        $this->actingAs($user)
            ->get('/')
            ->assertSuccessful()
            ->assertSee('Lanjutkan Pendaftaran');
    }

    public function test_landing_shows_login_and_register_for_guest(): void
    {
        $this->get('/')
            ->assertSuccessful()
            ->assertSee('Masuk ke Akun')
            ->assertSee('Pendaftaran Transportir');
    }
}
