<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Elemind\PressFilamentTheme\Enums\PressVariant;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Broadsheet memakai Figtree (sans) dan Inter (heading), menggantikan
 * Instrument Sans / Instrument Serif bawaan preset Press.
 *
 * Nilai font di-override lewat bootUsing() karena PressFilamentTheme::boot()
 * menimpa ->font() pada rantai panel().
 */
class PanelFontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * @return array{0: Panel, 1: object}
     */
    private function bootPanelFor(string $panelId, PressVariant $variant): array
    {
        $panel = Filament::getPanel($panelId);
        $panel->boot();

        $plugin = $panel->getPlugin('press-filament-theme');
        $plugin->variantResolver(fn (): PressVariant => $variant);

        return [$panel, $plugin];
    }

    private function resetVariantResolver(string $panelId): void
    {
        Filament::getPanel($panelId)
            ->getPlugin('press-filament-theme')
            ->variantResolver(fn () => null);
    }

    public function test_admin_broadsheet_uses_figtree_and_inter(): void
    {
        [$panel] = $this->bootPanelFor('admin', PressVariant::Broadsheet);

        $this->assertSame('Figtree', $panel->getFontFamily());
        $this->assertSame('Inter', $panel->getSerifFontFamily());

        $this->resetVariantResolver('admin');
    }

    public function test_admin_other_variants_keep_their_own_sans(): void
    {
        [$panel] = $this->bootPanelFor('admin', PressVariant::Vellum);

        $this->assertSame('Figtree', $panel->getFontFamily());

        $this->resetVariantResolver('admin');

        [$panel] = $this->bootPanelFor('admin', PressVariant::Telex);

        $this->assertSame('IBM Plex Sans', $panel->getFontFamily());
        // Telex tidak punya serif; tidak boleh dipaksa Inter.
        $this->assertNotSame('Inter', $panel->getSerifFontFamily());

        $this->resetVariantResolver('admin');
    }

    public function test_transportir_broadsheet_uses_figtree_and_inter(): void
    {
        [$panel] = $this->bootPanelFor('transportir', PressVariant::Broadsheet);

        $this->assertSame('Figtree', $panel->getFontFamily());
        $this->assertSame('Inter', $panel->getSerifFontFamily());

        $this->resetVariantResolver('transportir');
    }

    public function test_broadsheet_html_has_no_instrument_serif(): void
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $user->assignRole('super_admin');

        $panel = Filament::getPanel('admin');
        $panel->boot();
        $panel->getPlugin('press-filament-theme')
            ->variantResolver(fn (): PressVariant => PressVariant::Broadsheet);

        $html = $this->actingAs($user)->get('/admin')->getContent();

        $this->assertStringNotContainsString('Instrument Serif', $html);
        $this->assertStringNotContainsString('instrument-serif', $html);
        $this->assertStringContainsString("--press-font-serif: 'Inter'", $html);
        $this->assertStringContainsString("--font-family: 'Figtree'", $html);
        $this->assertStringContainsString("--serif-font-family: 'Inter'", $html);

        $this->resetVariantResolver('admin');
    }
}
