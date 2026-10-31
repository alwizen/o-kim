<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsIconAlias;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tombol buka/tutup sidebar memakai ikon bars-3, bukan chevron.
 *
 * Chevron mudah disalahartikan sebagai tombol kembali/maju, padahal fungsinya
 * membuka dan menutup sidebar.
 */
class SidebarToggleIconTest extends TestCase
{
    use RefreshDatabase;

    /** Path SVG Heroicons yang dipakai untuk memastikan ikon benar-benar ter-render. */
    private const PATH_BARS_3_BOTTOM_RIGHT = 'M3.75 6.75h16.5M3.75 12h16.5M12 17.25h8.25';

    private const PATH_BARS_3_BOTTOM_LEFT = 'M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12';

    private const PATH_CHEVRON_RIGHT = 'm8.25 4.5 7.5 7.5-7.5 7.5';

    private const PATH_CHEVRON_LEFT = 'M15.75 19.5 8.25 12l7.5-7.5';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function topbarHtmlFor(string $panelId, string $role): string
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $user->assignRole($role);
        $this->actingAs($user);

        $panel = Filament::getPanel($panelId);
        $panel->boot();
        Filament::setCurrentPanel($panel);

        return Livewire::test($panel->getTopbarLivewireComponent())->html();
    }

    public function test_icon_aliases_are_registered_to_bars_3(): void
    {
        $this->assertSame(
            Heroicon::OutlinedBars3BottomRight,
            \Filament\Support\Facades\FilamentIcon::resolve(PanelsIconAlias::SIDEBAR_EXPAND_BUTTON),
        );

        $this->assertSame(
            Heroicon::OutlinedBars3BottomLeft,
            \Filament\Support\Facades\FilamentIcon::resolve(PanelsIconAlias::SIDEBAR_COLLAPSE_BUTTON),
        );
    }

    public function test_admin_toggle_buttons_use_bars_3_paths(): void
    {
        $html = $this->topbarHtmlFor('admin', 'super_admin');

        $this->assertStringContainsString(self::PATH_BARS_3_BOTTOM_RIGHT, $html);
        $this->assertStringContainsString(self::PATH_BARS_3_BOTTOM_LEFT, $html);

        $this->assertStringNotContainsString(self::PATH_CHEVRON_RIGHT, $html);
        $this->assertStringNotContainsString(self::PATH_CHEVRON_LEFT, $html);
    }

    public function test_transportir_uses_plain_bars_3_without_chevron(): void
    {
        // Panel transportir tidak memakai sidebarCollapsibleOnDesktop(), sehingga
        // tombolnya adalah toggle mobile biasa (bars-3), bukan pasangan buka/tutup.
        // Yang penting: chevron tidak dipakai di sana.
        $html = $this->topbarHtmlFor('transportir', 'transportir');

        $this->assertStringContainsString('fi-topbar-open-sidebar-btn', $html);
        $this->assertStringNotContainsString(self::PATH_CHEVRON_RIGHT, $html);
        $this->assertStringNotContainsString(self::PATH_CHEVRON_LEFT, $html);
    }

    public function test_open_button_uses_bottom_right_and_close_uses_bottom_left(): void
    {
        $html = $this->topbarHtmlFor('admin', 'super_admin');

        $open = strpos($html, 'fi-topbar-open-collapse-sidebar-btn');
        $close = strpos($html, 'fi-topbar-close-collapse-sidebar-btn');

        $this->assertNotFalse($open, 'Tombol buka sidebar tidak ditemukan.');
        $this->assertNotFalse($close, 'Tombol tutup sidebar tidak ditemukan.');

        $openIcon = substr($html, $open, 1200);
        $closeIcon = substr($html, $close, 1200);

        $this->assertStringContainsString(self::PATH_BARS_3_BOTTOM_RIGHT, $openIcon);
        $this->assertStringContainsString(self::PATH_BARS_3_BOTTOM_LEFT, $closeIcon);
    }
}
