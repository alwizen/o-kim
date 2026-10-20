<?php

namespace Tests\Feature;

use App\Actions\User\ActivateUser;
use App\Actions\User\RejectInternalRegistration;
use App\Enums\AccountStatus;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Transporter;
use App\Models\User;
use App\Notifications\InternalAccountApproved;
use App\Notifications\InternalAccountRejected;
use App\Notifications\InternalRegistrationSubmitted;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class InternalRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->superAdmin = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->superAdmin->assignRole('super_admin');
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Petugas Baru',
            'email' => 'petugas@okim.test',
            'role' => 'hsse',
            'password' => 'SandiRahasia1',
            'password_confirmation' => 'SandiRahasia1',
        ], $overrides);
    }

    // ---------- Pendaftaran ----------

    public function test_registration_page_is_accessible_and_shows_roles(): void
    {
        $this->get(route('register.internal'))
            ->assertSuccessful()
            ->assertSee('Daftar Petugas Internal')
            ->assertSee('HSE')
            ->assertSee('Distribusi')
            ->assertSee('QQ')
            ->assertSee('Fuel Terminal Manager')
            ->assertSee('Admin Verifikasi');
    }

    public function test_registration_does_not_offer_super_admin(): void
    {
        $this->get(route('register.internal'))
            ->assertOk()
            ->assertDontSee('value="super_admin"');
    }

    public function test_new_internal_user_is_created_as_pending_with_chosen_role(): void
    {
        Notification::fake();

        $response = $this->post(route('register.internal'), $this->validPayload());

        $user = User::where('email', 'petugas@okim.test')->firstOrFail();

        $this->assertSame(AccountStatus::Pending, $user->account_status);
        $this->assertTrue($user->hasRole('hsse'));
        $this->assertFalse($user->hasRole('transportir'));
        $this->assertNull($user->email_verified_at);

        $response->assertRedirect('/');

        Notification::assertSentTo(
            User::where('email', $this->superAdmin->email)->first(),
            InternalRegistrationSubmitted::class,
        );
    }

    public function test_super_admin_role_cannot_be_registered(): void
    {
        $this->from(route('register.internal'))
            ->post(route('register.internal'), $this->validPayload(['role' => 'super_admin']))
            ->assertSessionHasErrors(['role']);

        $this->assertDatabaseMissing('users', ['email' => 'petugas@okim.test']);
    }

    public function test_transportir_role_cannot_be_registered_via_internal_form(): void
    {
        $this->from(route('register.internal'))
            ->post(route('register.internal'), $this->validPayload(['role' => 'transportir']))
            ->assertSessionHasErrors(['role']);

        $this->assertDatabaseMissing('users', ['email' => 'petugas@okim.test']);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $existing = User::factory()->create(['email' => 'petugas@okim.test']);

        $this->post(route('register.internal'), $this->validPayload())
            ->assertSessionHasErrors(['email']);

        $this->assertSame($existing->id, User::where('email', 'petugas@okim.test')->firstOrFail()->id);
    }

    public function test_weak_password_is_rejected(): void
    {
        $this->post(route('register.internal'), $this->validPayload(['password' => 'lemah', 'password_confirmation' => 'lemah']))
            ->assertSessionHasErrors(['password']);

        $this->assertDatabaseMissing('users', ['email' => 'petugas@okim.test']);
    }

    // ---------- Akses akun pending ----------

    public function test_pending_internal_user_cannot_access_admin_panel(): void
    {
        $this->post(route('register.internal'), $this->validPayload());
        $user = User::where('email', 'petugas@okim.test')->firstOrFail();

        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_pending_internal_user_sees_waiting_page(): void
    {
        $this->post(route('register.internal'), $this->validPayload());

        $user = User::where('email', 'petugas@okim.test')->firstOrFail();

        $this->actingAs($user)
            ->get(route('registration.pending.internal'))
            ->assertSuccessful()
            ->assertSee('Menunggu Verifikasi');
    }

    // ---------- Approval via Menu Pengguna ----------

    public function test_super_admin_can_approve_pending_internal_user(): void
    {
        Notification::fake();

        $this->post(route('register.internal'), $this->validPayload());
        $user = User::where('email', 'petugas@okim.test')->firstOrFail();

        $this->actingAs($this->superAdmin);

        Livewire::test(ListUsers::class)
            ->callTableAction('activate', $user)
            ->assertHasNoTableActionErrors();

        $user->refresh();

        $this->assertSame(AccountStatus::Approved, $user->account_status);
        $this->assertTrue($user->canAccessPanel(Filament::getPanel('admin')));

        Notification::assertSentTo($user, InternalAccountApproved::class);
    }

    public function test_super_admin_can_reject_pending_internal_user_with_reason(): void
    {
        Notification::fake();

        $this->post(route('register.internal'), $this->validPayload());
        $user = User::where('email', 'petugas@okim.test')->firstOrFail();

        $this->actingAs($this->superAdmin);

        Livewire::test(ListUsers::class)
            ->callTableAction('reject_registration', $user, data: ['reason' => 'Belum ada kebutuhan'])
            ->assertHasNoTableActionErrors();

        $user->refresh();

        $this->assertSame(AccountStatus::Rejected, $user->account_status);
        $this->assertSame('Belum ada kebutuhan', $user->rejection_reason);

        Notification::assertSentTo($user, InternalAccountRejected::class);
    }

    public function test_reject_action_does_not_target_transportir(): void
    {
        $transportir = User::factory()->create(['account_status' => AccountStatus::Pending]);
        $transportir->assignRole('transportir');
        $transportir->transporters()->attach(Transporter::factory()->create());

        $this->actingAs($this->superAdmin);

        // Aksi tolak hanya untuk pendaftaran internal, bukan transportir.
        try {
            app(RejectInternalRegistration::class)->execute($transportir, $this->superAdmin, 'x');
            $this->fail('Seharusnya ValidationException dilempar untuk akun transportir.');
        } catch (ValidationException) {
            // Diharapkan: transportir tidak dapat ditolak lewat aksi ini.
        }

        $transportir->refresh();

        $this->assertSame(AccountStatus::Pending, $transportir->account_status);
        $this->assertNull($transportir->rejection_reason);
    }

    public function test_non_super_admin_cannot_see_users_menu(): void
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $user->assignRole('hsse');

        $this->actingAs($user)
            ->get('/admin/users')
            ->assertForbidden();
    }

    // ---------- Integrasi login ----------

    public function test_login_page_links_to_internal_registration(): void
    {
        $this->get(route('login.form'))
            ->assertOk()
            ->assertSee(route('register.internal'));
    }

    public function test_approved_internal_user_is_redirected_to_admin_panel_after_login(): void
    {
        $this->post(route('register.internal'), $this->validPayload());
        $user = User::where('email', 'petugas@okim.test')->firstOrFail();
        app(ActivateUser::class)->execute($user);

        Auth::logout();

        $this->post(route('login.attempt'), [
            'email' => 'petugas@okim.test',
            'password' => 'SandiRahasia1',
        ])->assertRedirect('/admin');
    }
}
