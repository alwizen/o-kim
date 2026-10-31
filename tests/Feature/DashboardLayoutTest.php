<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Filament\Widgets\ApplicationTrendChart;
use App\Filament\Widgets\ExpiringDocumentsTable;
use App\Filament\Widgets\ExpiringDispensations;
use App\Filament\Widgets\FindingTrendChart;
use App\Filament\Widgets\FtmDecisionQueue;
use App\Filament\Widgets\FtmPendingDispensations;
use App\Filament\Widgets\InspectionsDueToday;
use App\Filament\Widgets\PendingDocumentVerification;
use App\Filament\Widgets\PengawasApprovalQueue;
use App\Filament\Widgets\RecentActivity;
use App\Filament\Widgets\TadInspectionQueue;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tata letak dashboard: Aktivitas Terbaru selalu di bawah, dan grup menu
 * Data & Profil berada di urutan terakhir sidebar admin.
 */
class DashboardLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $user->assignRole('super_admin');

        return $user;
    }

    public function test_recent_activity_sorts_after_every_other_admin_widget(): void
    {
        $others = [
            TadInspectionQueue::class,
            PengawasApprovalQueue::class,
            FtmDecisionQueue::class,
            PendingDocumentVerification::class,
            ExpiringDispensations::class,
            InspectionsDueToday::class,
            ExpiringDocumentsTable::class,
            FtmPendingDispensations::class,
            ApplicationTrendChart::class,
            FindingTrendChart::class,
        ];

        foreach ($others as $widget) {
            $this->assertGreaterThan(
                $widget::getSort(),
                RecentActivity::getSort(),
                $widget.' seharusnya tampil di atas Aktivitas Terbaru.',
            );
        }
    }

    public function test_recent_activity_keeps_its_description_and_limit(): void
    {
        $this->actingAs($this->superAdmin());

        $table = (new RecentActivity)->table(
            \Filament\Tables\Table::make(new RecentActivity)
        );

        $this->assertSame('10 notifikasi terakhir untuk Anda.', $table->getDescription());
    }

    public function test_admin_sidebar_puts_data_and_profil_group_last(): void
    {
        $html = $this->actingAs($this->superAdmin())->get('/admin')->getContent();

        preg_match_all('/fi-sidebar-group-label[^>]*>\s*([^<]+?)\s*</', $html, $matches);

        $groups = array_values(array_unique(array_map(
            fn (string $label): string => html_entity_decode(trim($label)),
            $matches[1] ?? [],
        )));

        $this->assertContains('Data & Profil', $groups);
        $this->assertSame('Data & Profil', end($groups), 'Grup Data & Profil harus paling bawah.');
    }

    public function test_profile_page_sits_in_the_last_group(): void
    {
        $this->assertSame('Data & Profil', \App\Filament\Pages\MyProfile::getNavigationGroup());
    }
}
