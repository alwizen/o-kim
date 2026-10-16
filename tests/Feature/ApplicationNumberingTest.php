<?php

namespace Tests\Feature;

use App\Enums\ApplicationType;
use App\Models\Transporter;
use App\Models\Vehicle;
use App\Services\Application\KimApplicationService;
use App\Services\Setting\SettingsService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationNumberingTest extends TestCase
{
    use RefreshDatabase;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $transporter = Transporter::factory()->create();
        $this->vehicle = Vehicle::factory()->create(['transporter_id' => $transporter->id]);
    }

    public function test_default_application_number_format(): void
    {
        $application = app(KimApplicationService::class)
            ->createDraft($this->vehicle, ApplicationType::New);

        $this->assertSame(
            'APP/'.now()->format('Ymd').'/0001',
            $application->application_number
        );
    }

    public function test_application_number_follows_settings(): void
    {
        app(SettingsService::class)->setMany([
            'application.number_prefix' => 'REG',
            'application.number_period_format' => 'Y',
            'application.number_sequence_digits' => '3',
        ], SettingsService::GROUP_KIM);

        $application = app(KimApplicationService::class)
            ->createDraft($this->vehicle, ApplicationType::New);

        $this->assertSame('REG/'.now()->format('Y').'/001', $application->application_number);
    }

    public function test_application_number_without_period(): void
    {
        app(SettingsService::class)->setMany([
            'application.number_prefix' => 'APP',
            'application.number_period_format' => 'none',
            'application.number_sequence_digits' => '4',
        ], SettingsService::GROUP_KIM);

        $application = app(KimApplicationService::class)
            ->createDraft($this->vehicle, ApplicationType::New);

        $this->assertSame('APP/0001', $application->application_number);
    }

    public function test_application_number_sequence_increments(): void
    {
        $second = Vehicle::factory()->create([
            'transporter_id' => $this->vehicle->transporter_id,
        ]);

        $service = app(KimApplicationService::class);

        $first = $service->createDraft($this->vehicle, ApplicationType::New);
        $secondApp = $service->createDraft($second, ApplicationType::New);

        $this->assertSame('APP/'.now()->format('Ymd').'/0001', $first->application_number);
        $this->assertSame('APP/'.now()->format('Ymd').'/0002', $secondApp->application_number);
    }

    public function test_application_numbers_are_unique(): void
    {
        $service = app(KimApplicationService::class);
        $numbers = [];

        for ($i = 0; $i < 5; $i++) {
            $vehicle = Vehicle::factory()->create(['transporter_id' => $this->vehicle->transporter_id]);
            $numbers[] = $service->createDraft($vehicle, ApplicationType::New)->application_number;
        }

        $this->assertCount(5, array_unique($numbers));
    }
}
