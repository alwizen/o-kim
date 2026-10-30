<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\InternalRole;
use App\Enums\NotificationEvent;
use App\Filament\Resources\NotificationSettings\NotificationSettingResource;
use App\Filament\Resources\NotificationSettings\Pages\EditNotificationSetting;
use App\Filament\Resources\NotificationSettings\Pages\ListNotificationSettings;
use App\Jobs\SendNotificationMail;
use App\Models\NotificationSetting;
use App\Models\Transporter;
use App\Models\User;
use App\Notifications\InspectionScheduledForStaff;
use App\Notifications\KimApplicationSubmitted;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\TransporterApproved;
use App\Services\Notification\NotificationSettingsService;
use Database\Seeders\NotificationSettingSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NotificationSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, NotificationSettingSeeder::class]);
        Queue::fake();
    }

    private function staff(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'account_status' => AccountStatus::Approved,
        ], $attributes));
        $user->assignRole($role);

        return $user;
    }

    private function transportir(): User
    {
        $user = $this->staff('transportir');
        $user->transporters()->attach(Transporter::factory()->create());

        return $user;
    }

    private function service(): NotificationSettingsService
    {
        return app(NotificationSettingsService::class);
    }

    // ---------- Registry & seeder ----------

    public function test_every_event_has_a_notification_class(): void
    {
        foreach (NotificationEvent::cases() as $event) {
            $class = $event->notificationClass();

            $this->assertNotNull($class, "Event {$event->value} tidak punya kelas notifikasi.");
            $this->assertTrue(class_exists($class), "Kelas {$class} tidak ditemukan.");
        }
    }

    public function test_notification_event_keys_are_unique(): void
    {
        $keys = array_map(fn (NotificationEvent $event) => $event->value, NotificationEvent::cases());

        $this->assertSame($keys, array_values(array_unique($keys)));
    }

    public function test_seeder_creates_row_for_every_event_and_is_idempotent(): void
    {
        $this->assertSame(NotificationEvent::cases(), array_map(
            fn (NotificationSetting $setting) => NotificationEvent::from($setting->event_key),
            NotificationSetting::query()->orderBy('id')->get()->all(),
        ));

        $this->seed(NotificationSettingSeeder::class);

        $this->assertSame(count(NotificationEvent::cases()), NotificationSetting::count());
    }

    public function test_seeder_preserves_super_admin_changes(): void
    {
        $this->service()->save(NotificationEvent::InternalApplicationSubmitted, false, ['ftm']);

        $this->seed(NotificationSettingSeeder::class);

        $this->assertFalse($this->service()->mailEnabled(NotificationEvent::InternalApplicationSubmitted));
        $this->assertSame(['ftm'], $this->service()->rolesFor(NotificationEvent::InternalApplicationSubmitted));
    }

    public function test_defaults_apply_when_row_is_missing(): void
    {
        NotificationSetting::query()->delete();
        $this->service()->flush();

        $this->assertTrue($this->service()->mailEnabled(NotificationEvent::InternalApplicationSubmitted));
        $this->assertSame(
            NotificationEvent::InternalApplicationSubmitted->defaultRoles(),
            $this->service()->rolesFor(NotificationEvent::InternalApplicationSubmitted),
        );
    }

    public function test_locked_events_cannot_be_disabled(): void
    {
        $locked = NotificationEvent::AccountPasswordReset;

        $this->service()->save($locked, false, []);

        $this->assertTrue($this->service()->mailEnabled($locked));
        $this->assertTrue($locked->locked());
    }

    // ---------- Gate email ----------

    public function test_email_is_queued_by_default(): void
    {
        $hsse = $this->staff('hsse');

        $hsse->notify(new KimApplicationSubmitted('APP-001', 'B 1 AA', 'PT Uji'));

        Queue::assertPushed(SendNotificationMail::class);
    }

    public function test_disabled_event_skips_email_but_keeps_database_notification(): void
    {
        $this->service()->save(NotificationEvent::InternalApplicationSubmitted, false, ['hsse']);
        $hsse = $this->staff('hsse');

        $hsse->notify(new KimApplicationSubmitted('APP-001', 'B 1 AA', 'PT Uji'));

        Queue::assertNotPushed(SendNotificationMail::class);
        $this->assertSame(1, $hsse->notifications()->count());
        $this->assertTrue(str_contains($hsse->notifications()->first()->data['title'], 'Pengajuan KIM Baru'));
    }

    public function test_role_not_selected_does_not_receive_email_but_keeps_bell(): void
    {
        // Admin menentukan hanya FTM yang menerima email pengajuan baru.
        $this->service()->save(NotificationEvent::InternalApplicationSubmitted, true, ['ftm']);

        $hsse = $this->staff('hsse');
        $ftm = $this->staff('ftm');

        $hsse->notify(new KimApplicationSubmitted('APP-001', 'B 1 AA', 'PT Uji'));
        $ftm->notify(new KimApplicationSubmitted('APP-002', 'B 2 BB', 'PT Uji'));

        Queue::assertPushed(SendNotificationMail::class, 1);
        $this->assertSame(1, $hsse->notifications()->count());
        $this->assertSame(1, $ftm->notifications()->count());
    }

    public function test_tad_role_can_be_limited_to_checklist_notifications_only(): void
    {
        // Super admin membatasi email pengajuan baru hanya ke admin verifikasi & FTM,
        // sehingga TAD HSSE hanya menerima email seputar checklist/jadwal.
        $this->service()->save(
            NotificationEvent::InternalApplicationSubmitted,
            true,
            ['admin_verifikasi', 'ftm'],
        );

        $hsse = $this->staff('hsse');

        $hsse->notify(new KimApplicationSubmitted('APP-001', 'B 1 AA', 'PT Uji'));
        Queue::assertNotPushed(SendNotificationMail::class);

        $hsse->notify(new InspectionScheduledForStaff('APP-001', 'B 1 AA', '20 Okt 2026 09:00'));
        Queue::assertPushed(SendNotificationMail::class, 1);
    }

    public function test_locked_event_still_sends_email_when_row_disabled(): void
    {
        $this->service()->save(NotificationEvent::AccountPasswordReset, false, []);

        Notification::fake();
        $user = $this->staff('hsse');
        $user->notify(new ResetPasswordNotification('token-uji'));

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_transportir_notification_respects_role_configuration(): void
    {
        $this->service()->save(NotificationEvent::TransporterAccountApproved, false, ['transportir']);
        $owner = $this->transportir();

        $owner->notify(new TransporterApproved);

        Queue::assertNotPushed(SendNotificationMail::class);
        $this->assertSame(1, $owner->notifications()->count());
    }

    // ---------- Resource / akses ----------

    public function test_resource_is_accessible_only_for_super_admin(): void
    {
        $this->actingAs($this->staff('super_admin'));
        $this->assertTrue(NotificationSettingResource::canAccess());

        foreach (['admin_verifikasi', 'hsse', 'ftm', 'transportir'] as $role) {
            $user = $this->staff($role);
            $this->actingAs($user);
            $this->assertFalse(NotificationSettingResource::canAccess(), "Role {$role} tidak boleh mengakses.");
        }
    }

    public function test_super_admin_without_permission_cannot_access_resource(): void
    {
        $user = $this->staff('super_admin');
        $user->roles()->first()->revokePermissionTo('manage_notification_settings');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($user->fresh());

        $this->assertFalse(NotificationSettingResource::canAccess());
    }

    public function test_super_admin_can_save_recipient_roles(): void
    {
        $admin = $this->staff('super_admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        $setting = NotificationSetting::where('event_key', NotificationEvent::InternalApplicationSubmitted->value)->firstOrFail();

        Livewire::test(EditNotificationSetting::class, ['record' => $setting->getRouteKey()])
            ->assertSuccessful()
            ->fillForm([
                'mail_enabled' => true,
                'roles' => ['admin_verifikasi', 'ftm'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(
            ['admin_verifikasi', 'ftm'],
            $this->service()->rolesFor(NotificationEvent::InternalApplicationSubmitted),
        );
    }

    public function test_super_admin_can_disable_email_for_an_event(): void
    {
        $admin = $this->staff('super_admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        $setting = NotificationSetting::where('event_key', NotificationEvent::TransporterKimIssued->value)->firstOrFail();

        Livewire::test(EditNotificationSetting::class, ['record' => $setting->getRouteKey()])
            ->fillForm([
                'mail_enabled' => false,
                'roles' => ['transportir'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($this->service()->mailEnabled(NotificationEvent::TransporterKimIssued));
    }

    public function test_locked_event_cannot_be_disabled_from_form(): void
    {
        $admin = $this->staff('super_admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        $setting = NotificationSetting::where('event_key', NotificationEvent::AccountPasswordReset->value)->firstOrFail();

        Livewire::test(EditNotificationSetting::class, ['record' => $setting->getRouteKey()])
            ->fillForm(['mail_enabled' => false, 'roles' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        // Field terkunci tidak ter-dehydrate, sehingga nilai tetap aktif.
        $this->assertTrue($this->service()->mailEnabled(NotificationEvent::AccountPasswordReset));
    }

    public function test_super_admin_can_restore_default_roles(): void
    {
        $admin = $this->staff('super_admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        $this->service()->save(NotificationEvent::InternalApplicationSubmitted, false, ['ftm']);
        $setting = NotificationSetting::where('event_key', NotificationEvent::InternalApplicationSubmitted->value)->firstOrFail();

        Livewire::test(EditNotificationSetting::class, ['record' => $setting->getRouteKey()])
            ->callAction('resetDefault')
            ->assertHasNoActionErrors();

        $this->assertTrue($this->service()->mailEnabled(NotificationEvent::InternalApplicationSubmitted));
        $this->assertSame(
            NotificationEvent::InternalApplicationSubmitted->defaultRoles(),
            $this->service()->rolesFor(NotificationEvent::InternalApplicationSubmitted),
        );
    }

    public function test_list_shows_all_recipient_roles_without_truncation(): void
    {
        $admin = $this->staff('super_admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $setting = NotificationSetting::where('event_key', NotificationEvent::InternalApplicationSubmitted->value)->firstOrFail();

        $roles = $setting->roleNames();
        $this->assertGreaterThan(4, count($roles), 'Prasyarat test: event ini memiliki lebih dari 4 role penerima.');

        $expectedLabels = collect($roles)
            ->map(fn (string $role): string => InternalRole::tryFrom($role)?->label()
                ?? str($role)->replace('_', ' ')->title()->toString())
            ->all();

        $this->actingAs($admin);

        $component = Livewire::test(ListNotificationSettings::class)
            ->assertSuccessful()
            ->assertDontSee('Lainnya');

        $component->assertSee($expectedLabels);
    }

    public function test_super_admin_can_open_list_and_edit_pages(): void
    {
        $admin = $this->staff('super_admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->actingAs($admin)
            ->get(NotificationSettingResource::getUrl('index'))
            ->assertSuccessful()
            ->assertSee('Notifikasi Email');

        $setting = NotificationSetting::where('event_key', NotificationEvent::InternalApplicationSubmitted->value)->firstOrFail();

        $this->actingAs($admin)
            ->get(NotificationSettingResource::getUrl('edit', ['record' => $setting]))
            ->assertSuccessful()
            ->assertSee('Role penerima email');
    }
}
