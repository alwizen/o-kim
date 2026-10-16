<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Filament\Pages\ManageSettings;
use App\Models\Setting;
use App\Models\User;
use App\Services\Setting\SettingsService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsTest extends TestCase
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

    public function test_defaults_apply_before_any_setting_exists(): void
    {
        $settings = app(SettingsService::class);

        $this->assertSame(6, $settings->kimValidityMonths());
        $this->assertSame('KIM/FT-TGL', $settings->kimNumberPrefix());
        $this->assertSame('Y/m', $settings->kimNumberPeriodFormat());
        $this->assertSame(4, $settings->kimNumberSequenceDigits());
    }

    public function test_setting_can_be_stored_and_read_back(): void
    {
        $settings = app(SettingsService::class);

        $settings->set('kim.validity_months', '24', SettingsService::GROUP_KIM);

        $this->assertSame(24, app(SettingsService::class)->kimValidityMonths());
        $this->assertDatabaseHas('settings', ['key' => 'kim.validity_months', 'value' => '24']);
    }

    public function test_cache_is_flushed_after_write(): void
    {
        $settings = app(SettingsService::class);

        $settings->kimValidityMonths();          // isi cache
        $settings->set('kim.validity_months', '12');

        $this->assertSame(12, app(SettingsService::class)->kimValidityMonths());
    }

    public function test_setting_seeder_is_idempotent_and_preserves_edits(): void
    {
        $this->seed(SettingSeeder::class);

        app(SettingsService::class)->set('kim.validity_months', '24');
        $this->seed(SettingSeeder::class);

        $this->assertSame(24, app(SettingsService::class)->kimValidityMonths());
        $this->assertSame(1, Setting::where('key', 'kim.validity_months')->count());
    }

    public function test_settings_page_requires_manage_settings_permission(): void
    {
        $transportir = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $transportir->assignRole('transportir');

        $this->actingAs($transportir)->get('/admin/manage-settings')->assertForbidden();
    }

    public function test_admin_can_open_settings_page(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/manage-settings')
            ->assertSuccessful();
    }

    public function test_admin_can_save_kim_settings_via_form(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(ManageSettings::class)
            ->fillForm([
                'kim_validity_months' => '12',
                'kim_number_prefix' => 'KIM/TEST',
                'kim_number_period_format' => 'Y',
                'kim_number_sequence_digits' => '5',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(SettingsService::class);

        $this->assertSame(12, $settings->kimValidityMonths());
        $this->assertSame('KIM/TEST', $settings->kimNumberPrefix());
        $this->assertSame('Y', $settings->kimNumberPeriodFormat());
        $this->assertSame(5, $settings->kimNumberSequenceDigits());
    }

    // ---------- Penomoran Pengajuan ----------

    public function test_application_number_defaults(): void
    {
        $settings = app(SettingsService::class);

        $this->assertSame('APP', $settings->applicationNumberPrefix());
        $this->assertSame('Ymd', $settings->applicationNumberPeriodFormat());
        $this->assertSame(4, $settings->applicationNumberSequenceDigits());
    }

    public function test_application_number_preview_uses_settings(): void
    {
        $settings = app(SettingsService::class);

        $this->assertSame(
            'APP/'.now()->format('Ymd').'/0001',
            $settings->buildNumberPreview('APP', 'Ymd', 4)
        );

        $this->assertSame(
            'REG/'.now()->format('Y').'/001',
            $settings->buildNumberPreview('REG', 'Y', 3)
        );

        $this->assertSame(
            'APP/0001',
            $settings->buildNumberPreview('APP', 'none', 4)
        );
    }

    public function test_admin_can_save_application_number_settings(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(ManageSettings::class)
            ->fillForm([
                'application_number_prefix' => 'REG',
                'application_number_period_format' => 'Y',
                'application_number_sequence_digits' => '3',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(SettingsService::class);

        $this->assertSame('REG', $settings->applicationNumberPrefix());
        $this->assertSame('Y', $settings->applicationNumberPeriodFormat());
        $this->assertSame(3, $settings->applicationNumberSequenceDigits());
    }
}
