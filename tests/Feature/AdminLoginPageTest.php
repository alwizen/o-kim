<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminLoginPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function approvedUser(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'account_status' => AccountStatus::Approved,
            'email' => 'adm@okim.test',
            'password' => 'SandiLama123',
        ], $attributes));
        $user->assignRole('super_admin');

        return $user;
    }

    public function test_login_page_uses_split_layout_like_the_public_login(): void
    {
        $response = $this->get('/admin/login')->assertSuccessful();

        $response->assertSee('lg:grid-cols-2', false);        // grid dua kolom
        $response->assertSee('bg-pastel', false);             // panel brand
        $response->assertSee('img/ppn.png', false);           // logo
        $response->assertSee('Masuk ke akun Anda');
        $response->assertSee('Gunakan email dan kata sandi akun internal Anda.');
        $response->assertSee('Kembali ke Halaman utama');
    }

    public function test_brand_panel_text_matches_public_login(): void
    {
        $this->get('/admin/login')
            ->assertSee('Kartu Izin Masuk Mobil Tangki, kini serba digital.');
    }

    public function test_brand_panel_lists_internal_duties(): void
    {
        $this->get('/admin/login')
            ->assertSee('Verifikasi pendaftaran transportir')
            ->assertSee('Pemeriksaan checklist per fungsi')
            ->assertSee('Approval berjenjang hingga KIM terbit');
    }

    public function test_login_page_has_no_google_button(): void
    {
        $this->get('/admin/login')
            ->assertDontSee('auth.google')
            ->assertDontSee('Masuk dengan Google');
    }

    public function test_password_reset_link_is_present(): void
    {
        $this->get('/admin/login')->assertSee('Lupa kata sandi?');
    }

    public function test_login_page_renders_email_and_password_fields(): void
    {
        $response = $this->get('/admin/login');

        $response->assertSee('form.email', false);
        $response->assertSee('form.password', false);
    }

    public function test_approved_user_can_authenticate(): void
    {
        $this->approvedUser();

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'adm@okim.test',
                'password' => 'SandiLama123',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticated();
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->approvedUser();

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'adm@okim.test',
                'password' => 'SalahSekali1',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public function test_pending_user_cannot_reach_the_panel_after_login(): void
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::Pending,
            'email' => 'pending@okim.test',
            'password' => 'SandiLama123',
        ]);
        $user->assignRole('hsse');

        $response = $this->post('/admin/login', [
            'email' => 'pending@okim.test',
            'password' => 'SandiLama123',
        ]);

        $this->assertFalse(
            $user->canAccessPanel(Filament::getPanel('admin')),
            'Akun pending tidak boleh lolos canAccessPanel.',
        );

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_public_login_page_is_unchanged(): void
    {
        $this->get('/masuk')
            ->assertSuccessful()
            ->assertSee('Daftar di sini')
            ->assertSee('Masuk dengan Google');
    }
}
