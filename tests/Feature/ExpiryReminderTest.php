<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApplicationStatus;
use App\Enums\DocumentVerificationStatus;
use App\Enums\KimCardStatus;
use App\Models\DocumentType;
use App\Models\KimApplication;
use App\Models\KimCard;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Notifications\ExpiryReminder;
use App\Notifications\ExpiryReminderStaff;
use App\Services\Document\DocumentExpiryService;
use App\Services\Setting\SettingsService;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\NotificationSettingSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ExpiryReminderTest extends TestCase
{
    use RefreshDatabase;

    private Transporter $transporter;

    private User $owner;

    private Vehicle $vehicle;

    private DocumentType $stnk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, NotificationSettingSeeder::class, DocumentTypeSeeder::class]);

        $this->transporter = Transporter::factory()->create(['company_name' => 'PT Uji Logistik']);

        $this->owner = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->owner->assignRole('transportir');
        $this->owner->transporters()->attach($this->transporter);

        $this->vehicle = Vehicle::factory()->create([
            'transporter_id' => $this->transporter->id,
            'status' => 'active',
        ]);

        $this->stnk = DocumentType::where('code', 'STNK')->firstOrFail();
    }

    private function document(int $expiresInDays): VehicleDocument
    {
        return VehicleDocument::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'document_type_id' => $this->stnk->id,
            'expires_at' => now()->addDays($expiresInDays)->toDateString(),
            'verification_status' => DocumentVerificationStatus::Verified,
            'replaced_by_id' => null,
        ]);
    }

    private function staff(): User
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $user->assignRole('admin_verifikasi');

        return $user;
    }

    public function test_digest_is_sent_to_transporter_and_staff(): void
    {
        Notification::fake();
        Cache::flush();

        $this->document(20);
        $staff = $this->staff();

        $this->artisan('okim:notify-expiry')->assertSuccessful();

        Notification::assertSentTo($this->owner, ExpiryReminder::class);
        Notification::assertSentTo($staff, ExpiryReminderStaff::class);
    }

    public function test_far_future_documents_are_ignored(): void
    {
        Notification::fake();
        Cache::flush();

        $this->document(200);

        $this->artisan('okim:notify-expiry')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_digest_is_not_sent_twice_in_the_same_day(): void
    {
        Notification::fake();
        Cache::flush();

        $this->document(10);

        $this->artisan('okim:notify-expiry')->assertSuccessful();
        $this->artisan('okim:notify-expiry')->assertSuccessful();

        Notification::assertSentToTimes($this->owner, ExpiryReminder::class, 1);
    }

    public function test_threshold_follows_system_setting(): void
    {
        Notification::fake();
        Cache::flush();

        app(SettingsService::class)->set('notification.expiry_warning_days', '15');

        $this->document(20);

        $this->artisan('okim:notify-expiry')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertSame(15, app(SettingsService::class)->expiryWarningDays());
    }

    public function test_expired_document_is_included_in_digest(): void
    {
        Notification::fake();
        Cache::flush();

        $this->document(-3);

        $this->artisan('okim:notify-expiry')->assertSuccessful();

        Notification::assertSentTo($this->owner, ExpiryReminder::class, function (ExpiryReminder $notification): bool {
            return count($notification->items) === 1
                && $notification->items[0]['days_left'] === -3;
        });
    }

    public function test_expiring_kim_card_is_included_in_digest(): void
    {
        Notification::fake();
        Cache::flush();

        $application = KimApplication::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'transporter_id' => $this->transporter->id,
            'status' => ApplicationStatus::Issued,
        ]);

        KimCard::create([
            'kim_application_id' => $application->id,
            'kim_number' => 'KIM/FT-TGL/2026/10/0001',
            'issued_at' => now()->subMonths(5),
            'expires_at' => now()->addDays(12)->toDateString(),
            'qr_token' => str_repeat('a', 48),
            'status' => KimCardStatus::Active,
        ]);

        $this->artisan('okim:notify-expiry')->assertSuccessful();

        Notification::assertSentTo($this->owner, ExpiryReminder::class, function (ExpiryReminder $notification): bool {
            return collect($notification->items)->contains(fn (array $item) => str_contains($item['label'], 'KIM/FT-TGL'));
        });
    }

    public function test_transporter_digest_groups_all_company_items_in_one_email(): void
    {
        Notification::fake();
        Cache::flush();

        $this->document(5);
        $this->document(30);

        $this->artisan('okim:notify-expiry')->assertSuccessful();

        Notification::assertSentToTimes($this->owner, ExpiryReminder::class, 1);

        Notification::assertSentTo($this->owner, ExpiryReminder::class, function (ExpiryReminder $notification): bool {
            $days = collect($notification->items)->pluck('days_left')->all();

            return $days === [5, 30]; // terurut dari yang paling mendesak
        });
    }

    public function test_scheduler_registers_daily_command(): void
    {
        $schedule = app(Schedule::class);

        $event = collect($schedule->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'okim:notify-expiry'));

        $this->assertNotNull($event);
        $this->assertSame('0 7 * * *', $event->expression);
    }

    public function test_days_until_is_stable_for_today_expiry(): void
    {
        $this->assertSame(0, DocumentExpiryService::daysUntil(Carbon::today()));
    }
}
