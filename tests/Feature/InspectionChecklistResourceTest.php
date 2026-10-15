<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\Department;
use App\Filament\Resources\InspectionChecklists\Pages\CreateInspectionChecklist;
use App\Filament\Resources\InspectionChecklists\Pages\EditInspectionChecklist;
use App\Filament\Resources\InspectionChecklists\Pages\ListInspectionChecklists;
use App\Filament\Resources\InspectionChecklists\RelationManagers\ItemsRelationManager;
use App\Models\InspectionChecklist;
use App\Models\InspectionItem;
use App\Models\User;
use Database\Seeders\InspectionChecklistSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InspectionChecklistResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $user->assignRole('super_admin');

        return $user;
    }

    private function officer(string $role): User
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $user->assignRole($role);

        return $user;
    }

    public function test_super_admin_can_access_checklist_resource(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/inspection-checklists')
            ->assertSuccessful();
    }

    public function test_officer_without_permission_cannot_access_resource(): void
    {
        // hsse punya inspect_hsse tetapi bukan manage_checklists
        $this->actingAs($this->officer('hsse'))
            ->get('/admin/inspection-checklists')
            ->assertForbidden();
    }

    public function test_admin_can_create_checklist(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateInspectionChecklist::class)
            ->fillForm([
                'department' => Department::Hsse->value,
                'code' => 'HSSE-UJI',
                'title' => 'Checklist Uji HSSE',
                'is_required' => true,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('inspection_checklists', [
            'code' => 'HSSE-UJI',
            'department' => 'hsse',
        ]);
    }

    public function test_admin_can_update_checklist(): void
    {
        $checklist = InspectionChecklist::create([
            'department' => Department::Qq,
            'code' => 'QQ-UJI',
            'title' => 'Judul Awal',
        ]);

        $this->actingAs($this->admin());

        Livewire::test(EditInspectionChecklist::class, ['record' => $checklist->getRouteKey()])
            ->fillForm(['title' => 'Judul Diubah'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Judul Diubah', $checklist->fresh()->title);
    }

    public function test_admin_can_delete_checklist(): void
    {
        $checklist = InspectionChecklist::create([
            'department' => Department::Qq,
            'code' => 'QQ-HAPUS',
            'title' => 'Akan Dihapus',
        ]);

        $this->actingAs($this->admin());

        Livewire::test(ListInspectionChecklists::class)
            ->callTableAction(DeleteAction::class, $checklist);

        $this->assertDatabaseMissing('inspection_checklists', ['id' => $checklist->id]);
    }

    public function test_checklist_code_must_be_unique(): void
    {
        InspectionChecklist::create([
            'department' => Department::Hsse,
            'code' => 'HSSE-STD',
            'title' => 'Ada',
        ]);

        $this->actingAs($this->admin());

        Livewire::test(CreateInspectionChecklist::class)
            ->fillForm([
                'department' => Department::Hsse->value,
                'code' => 'HSSE-STD',
                'title' => 'Duplikat',
            ])
            ->call('create')
            ->assertHasFormErrors(['code']);
    }

    public function test_item_and_points_can_be_created_via_relation_manager(): void
    {
        $checklist = InspectionChecklist::create([
            'department' => Department::Hsse,
            'code' => 'HSSE-RM',
            'title' => 'Checklist RM',
        ]);

        $this->actingAs($this->admin());

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $checklist,
            'pageClass' => EditInspectionChecklist::class,
        ])
            ->callTableAction(CreateAction::class, data: [
                'item_code' => 'HSSE-99',
                'label' => 'Sakelar Keamanan / Safety Switch',
                'sort_order' => 1,
                'is_required' => true,
                'is_active' => true,
                'points' => [
                    ['label' => 'Dipasang pada dashboard di dalam kabin', 'is_active' => true],
                    ['label' => 'Dipasang pada sisi pengemudi dekat accu', 'is_active' => true],
                ],
            ])
            ->assertHasNoTableActionErrors();

        $item = InspectionItem::where('item_code', 'HSSE-99')->first();
        $this->assertNotNull($item);
        $this->assertSame(2, $item->points()->count());
        $this->assertDatabaseHas('inspection_item_points', [
            'inspection_item_id' => $item->id,
            'label' => 'Dipasang pada dashboard di dalam kabin',
        ]);
    }

    public function test_deleting_item_cascades_points(): void
    {
        $checklist = InspectionChecklist::create([
            'department' => Department::Hsse,
            'code' => 'HSSE-CASCADE',
            'title' => 'Cascade',
        ]);
        $item = $checklist->items()->create(['item_code' => 'X-1', 'label' => 'Butir']);
        $point = $item->points()->create(['label' => 'Sub-butir']);

        $item->delete();

        $this->assertDatabaseMissing('inspection_item_points', ['id' => $point->id]);
    }

    public function test_seeder_creates_points_from_spec(): void
    {
        $this->seed(InspectionChecklistSeeder::class);

        $item = InspectionItem::where('item_code', 'HSSE-01')->first();

        $this->assertNotNull($item);
        $this->assertSame('Sakelar Keamanan / Safety Switch', $item->label);
        $this->assertGreaterThanOrEqual(3, $item->points()->count());
        $this->assertDatabaseHas('inspection_item_points', [
            'label' => 'Dipasang pada dashboard di dalam kabin',
        ]);
    }
}
