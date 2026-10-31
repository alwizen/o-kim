<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Color scheme biru diterapkan ke tema Press (Broadsheet/Vellum/Telex).
 *
 * Preset Press mendeklarasikan aksennya sendiri pada :root (Broadsheet memakai
 * merah koran). Override :root dari hook STYLES_AFTER membuat panel memakai
 * aksen biru tanpa mengubah paket vendor.
 */
class PressBluePaletteTest extends TestCase
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

    public function test_primary_accent_is_blue_not_masthead_red(): void
    {
        $html = $this->dashboardHtml();

        // #1C4D8D dalam oklch.
        $this->assertStringContainsString('--press-primary-600: oklch(42.3% 0.118 256.6)', $html);

        // Merah koran Broadsheet tidak boleh dipakai lagi.
        $this->assertStringNotContainsString('--press-primary-600: oklch(52.3% .155 32)', $html);
        $this->assertStringNotContainsString('--press-primary-600: oklch(0.523 0.155 32)', $html);
    }

    public function test_neutral_scale_uses_blue_hue(): void
    {
        $html = $this->dashboardHtml();

        foreach ([
            '--press-neutral-50: oklch(98.4% 0.005 240)',
            '--press-neutral-500: oklch(55.5% 0.026 252)',
            '--press-neutral-950: oklch(15.5% 0.020 262)',
        ] as $token) {
            $this->assertStringContainsString($token, $html);
        }
    }

    public function test_light_surfaces_follow_the_blue_cast(): void
    {
        $html = $this->dashboardHtml();

        $this->assertStringContainsString('--press-bg-app: oklch(97.4% 0.008 240)', $html);
        $this->assertStringContainsString('--press-bg-surface: oklch(99.2% 0.003 240)', $html);
    }

    public function test_override_is_rendered_after_press_stylesheet(): void
    {
        $html = $this->dashboardHtml();

        $pressPos = strpos($html, 'press-broadsheet.css');
        $overridePos = strpos($html, '--press-primary-600: oklch(42.3%');

        $this->assertNotFalse($pressPos, 'Stylesheet Press tidak termuat.');
        $this->assertNotFalse($overridePos, 'Override palet tidak termuat.');
        $this->assertGreaterThan(
            $pressPos,
            $overridePos,
            'Override harus berada setelah stylesheet Press agar menang.',
        );
    }
}
