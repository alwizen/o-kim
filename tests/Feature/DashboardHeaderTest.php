<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Filament\Pages\Dashboard;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardHeaderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function approvedUser(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(
            ['account_status' => AccountStatus::Approved],
            $attributes,
        ));
        $user->assignRole('super_admin');

        return $user;
    }

    public function test_dashboard_title_is_dashboard_not_dasbor(): void
    {
        $this->assertSame('Dashboard', Dashboard::getNavigationLabel());
        $this->assertSame('Dashboard', (new Dashboard)->getTitle());
    }

    public function test_dashboard_page_renders_dashboard_heading(): void
    {
        $this->actingAs($this->approvedUser(['name' => 'Budi Santoso', 'jabatan' => 'Safety Officer']))
            ->get('/admin')
            ->assertSuccessful()
            ->assertSee('Dashboard')
            ->assertDontSee('Dasbor');
    }

    public function test_subheading_shows_welcome_with_name_and_jabatan(): void
    {
        $this->actingAs($this->approvedUser(['name' => 'Budi Santoso', 'jabatan' => 'Safety Officer']));

        $this->assertSame(
            'Selamat datang, Budi Santoso — Safety Officer',
            (new Dashboard)->getSubheading(),
        );
    }

    public function test_subheading_shows_name_only_when_jabatan_empty(): void
    {
        $this->actingAs($this->approvedUser(['name' => 'Siti Aminah', 'jabatan' => null]));

        $this->assertSame('Selamat datang, Siti Aminah', (new Dashboard)->getSubheading());
    }

    public function test_subheading_is_null_without_authenticated_user(): void
    {
        $this->assertNull((new Dashboard)->getSubheading());
    }

    public function test_transportir_dashboard_also_uses_new_title(): void
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::Approved,
            'name' => 'Andi Wijaya',
            'jabatan' => 'Staf Operasional',
        ]);
        $user->assignRole('transportir');

        $this->actingAs($user)
            ->get('/transportir')
            ->assertSuccessful()
            ->assertSee('Selamat datang, Andi Wijaya — Staf Operasional');
    }
}
