<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\InternalRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleTableNumberingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $user->assignRole('super_admin');

        return $user;
    }

    /**
     * Ambil nomor urut yang tampil pada kolom "no" untuk setiap baris, sesuai urutan tampil.
     *
     * @return array<int, string>
     */
    private function renderedNumbers(string $html): array
    {
        preg_match_all('/column\.no.*?<div class="fi-ta-text[^"]*">(\d+)<\/div>/s', $html, $matches);

        return $matches[1];
    }

    public function test_roles_table_has_number_column_labelled_no(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(ListRoles::class)
            ->assertSuccessful()
            ->assertTableColumnExists('no', fn ($column): bool => $column->getLabel() === 'No');
    }

    public function test_number_column_starts_at_one_and_increments_per_row(): void
    {
        $this->actingAs($this->superAdmin());

        $html = Livewire::test(ListRoles::class)->assertSuccessful()->html();

        $numbers = $this->renderedNumbers($html);

        $this->assertNotEmpty($numbers);
        $this->assertSame('1', $numbers[0]);
        $this->assertSame(
            range(1, count($numbers)),
            array_map('intval', $numbers),
            'Nomor urut harus berurutan 1, 2, 3, ...',
        );
    }

    public function test_numbering_follows_sorted_role_order(): void
    {
        $this->actingAs($this->superAdmin());

        $html = Livewire::test(ListRoles::class)
            ->sortTable('name')
            ->assertSuccessful()
            ->html();

        $roles = Role::query()->where('guard_name', 'web')->orderBy('name')->pluck('name');

        // Baris pertama setelah pengurutan adalah role dengan nama terkecil (admin_verifikasi → baru 1).
        preg_match_all('/fi-ta-text-item fi-size-sm">([^<]+)<\/div>/s', $html, $matches);

        $expectedFirstRole = InternalRole::tryFrom($roles->first())?->label() ?? $roles->first();

        $this->assertContains($expectedFirstRole, $matches[1]);
        $this->assertSame('1', $this->renderedNumbers($html)[0]);
    }
}
