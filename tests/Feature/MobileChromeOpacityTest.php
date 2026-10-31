<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Permukaan sidebar/topbar dibuat hampir pekat di layar kecil.
 *
 * Tema Press (Vellum) berniat menampilkan kaca tembus pandang. Di layar kecil,
 * konten yang lewat di belakang topbar dan sidebar membuat label sulit dibaca,
 * sehingga di bawah breakpoint lg latar belakangnya dibuat pekat. Di desktop
 * efek kaca tetap dipertahankan.
 */
class MobileChromeOpacityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function dashboardHtml(): string
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $user->assignRole('super_admin');

        $this->actingAs($user);

        $panel = Filament::getPanel('admin');
        $panel->boot();

        return $this->get('/admin')->getContent();
    }

    public function test_mobile_override_is_rendered(): void
    {
        $html = $this->dashboardHtml();

        $this->assertStringContainsString('@media (max-width: 1023.98px)', $html);
        $this->assertStringContainsString('.fi-topbar {', $html);
        $this->assertStringContainsString('.fi-sidebar {', $html);
    }

    public function test_override_scopes_opacity_to_small_screens_only(): void
    {
        $html = $this->dashboardHtml();

        $mediaPos = strpos($html, '@media (max-width: 1023.98px)');

        // Blok override harus berada di dalam media query, bukan aturan global.
        $topbarRule = strpos($html, '.fi-topbar {', $mediaPos);
        $sidebarRule = strpos($html, '.fi-sidebar {', $mediaPos);

        $this->assertNotFalse($topbarRule);
        $this->assertNotFalse($sidebarRule);
        $this->assertGreaterThan($mediaPos, $topbarRule);
        $this->assertGreaterThan($mediaPos, $sidebarRule);
    }

    public function test_backdrop_blur_is_disabled_on_mobile_chrome(): void
    {
        $html = $this->dashboardHtml();

        $this->assertStringContainsString('backdrop-filter: none !important;', $html);
    }

    public function test_login_brand_description_targets_internal_staff(): void
    {
        $this->get('/admin/login')
            ->assertSee('Verifikasi pendaftaran transportir, jalankan checklist per fungsi, tindak lanjuti temuan')
            ->assertDontSee('Kelola profil perusahaan, data kendaraan, dokumen, hingga pengajuan KIM');
    }
}
