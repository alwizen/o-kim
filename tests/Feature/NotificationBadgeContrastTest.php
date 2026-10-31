<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

/**
 * Badge jumlah notifikasi pada ikon lonceng harus tetap terbaca di dark mode.
 *
 * Badge bawaan Filament memakai latar gray transparan dengan teks abu-abu,
 * sehingga nyaris tak terlihat di atas topbar gelap.
 */
class NotificationBadgeContrastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function adminWithUnread(): User
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $user->assignRole('super_admin');

        DatabaseNotification::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\\Notifications\\TransporterRegistered',
            'notifiable_type' => $user::class,
            'notifiable_id' => $user->getKey(),
            'data' => ['title' => 'Uji', 'body' => 'Notifikasi uji'],
            'read_at' => null,
        ]);

        return $user;
    }

    private function dashboardHtml(): string
    {
        $this->actingAs($this->adminWithUnread());

        $panel = Filament::getPanel('admin');
        $panel->boot();

        return $this->get('/admin')->getContent();
    }

    public function test_bell_badge_has_solid_background_in_light_mode(): void
    {
        $html = $this->dashboardHtml();

        $this->assertStringContainsString(
            '.fi-topbar-database-notifications-btn .fi-icon-btn-badge-ctn .fi-badge',
            $html,
        );
        $this->assertStringContainsString('background-color: #b03626', $html);
    }

    public function test_bell_badge_stays_readable_in_dark_mode(): void
    {
        $html = $this->dashboardHtml();

        $this->assertStringContainsString(
            '.dark .fi-topbar-database-notifications-btn .fi-icon-btn-badge-ctn .fi-badge',
            $html,
        );
        $this->assertStringContainsString('background-color: #e0523c', $html);
    }

    public function test_badge_text_is_forced_white_in_both_modes(): void
    {
        $html = $this->dashboardHtml();

        // Dua deklarasi `color: #fff` — satu untuk mode terang, satu untuk gelap.
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($html, 'color: #fff;'),
            'Badge harus memakai teks putih pada kedua mode.',
        );
    }

    public function test_badge_container_separates_badge_from_topbar(): void
    {
        $html = $this->dashboardHtml();

        $this->assertStringContainsString('.fi-topbar-database-notifications-btn .fi-icon-btn-badge-ctn', $html);
        $this->assertStringContainsString('background-color: var(--press-bg-topbar);', $html);
    }
}
