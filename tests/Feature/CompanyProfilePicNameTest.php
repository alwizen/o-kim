<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Filament\Transportir\Pages\CompanyProfile;
use App\Models\Transporter;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CompanyProfilePicNameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function approvedTransportir(string $name = 'Nama Google'): array
    {
        $transporter = Transporter::factory()->create();
        $user = User::factory()->create([
            'name' => $name,
            'account_status' => AccountStatus::Approved,
        ]);
        $user->assignRole('transportir');
        $user->transporters()->attach($transporter);

        return [$user, $transporter];
    }

    public function test_profile_form_is_prefilled_with_current_pic_name(): void
    {
        [$user] = $this->approvedTransportir('Nama Google');

        $this->actingAs($user);

        Livewire::test(CompanyProfile::class)
            ->assertFormSet(['pic_name' => 'Nama Google']);
    }

    public function test_transportir_can_update_pic_name(): void
    {
        [$user, $transporter] = $this->approvedTransportir('Nama Google');

        $this->actingAs($user);

        Livewire::test(CompanyProfile::class)
            ->fillForm([
                'pic_name' => 'Budi Santoso',
                'company_name' => $transporter->company_name,
                'company_address' => $transporter->company_address,
                'phone' => $transporter->phone,
                'legal_identifier' => $transporter->legal_identifier,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Budi Santoso', $user->fresh()->name);
    }

    public function test_pic_name_is_required(): void
    {
        [$user, $transporter] = $this->approvedTransportir();

        $this->actingAs($user);

        Livewire::test(CompanyProfile::class)
            ->fillForm([
                'pic_name' => '',
                'company_name' => $transporter->company_name,
                'company_address' => $transporter->company_address,
                'phone' => $transporter->phone,
            ])
            ->call('save')
            ->assertHasFormErrors(['pic_name']);
    }

    public function test_company_profile_update_does_not_break_transporter_data(): void
    {
        [$user, $transporter] = $this->approvedTransportir();

        $this->actingAs($user);

        Livewire::test(CompanyProfile::class)
            ->fillForm([
                'pic_name' => 'Nama Baru',
                'company_name' => 'PT Baru Jaya',
                'company_address' => $transporter->company_address,
                'phone' => $transporter->phone,
                'legal_identifier' => $transporter->legal_identifier,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Nama Baru', $user->fresh()->name);
        $this->assertSame('PT Baru Jaya', $transporter->fresh()->company_name);
    }
}
