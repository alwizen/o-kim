<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Filament\Pages\MyProfile;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class MyProfilePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function internal(string $role = 'hsse', array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'account_status' => AccountStatus::Approved,
            'name' => 'Budi Santoso',
            'email' => 'budi@okim.test',
            'password' => 'SandiLama123',
            'jabatan' => 'Safety Officer',
        ], $attributes));

        $user->assignRole($role);

        return $user;
    }

    public function test_internal_user_can_open_profile_page(): void
    {
        $this->actingAs($this->internal())
            ->get('/admin/my-profile')
            ->assertSuccessful()
            ->assertSee('Profil Saya')
            ->assertSee('Data Akun')
            ->assertSee('Ubah Kata Sandi');
    }

    public function test_form_is_prefilled_with_current_data(): void
    {
        $user = $this->internal();

        $this->actingAs($user);

        Livewire::test(MyProfile::class)
            ->assertFormSet([
                'name' => 'Budi Santoso',
                'email' => 'budi@okim.test',
            ]);
    }

    public function test_user_can_change_name_and_email(): void
    {
        $user = $this->internal();
        $this->actingAs($user);

        Livewire::test(MyProfile::class)
            ->fillForm([
                'name' => 'Budi Santoso Baru',
                'email' => 'budi.baru@okim.test',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();

        $this->assertSame('Budi Santoso Baru', $user->name);
        $this->assertSame('budi.baru@okim.test', $user->email);
    }

    public function test_email_change_clears_email_verification(): void
    {
        $user = $this->internal(attributes: ['email_verified_at' => now()]);
        $this->actingAs($user);

        Livewire::test(MyProfile::class)
            ->fillForm(['email' => 'baru@okim.test'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_email_can_be_saved_unchanged(): void
    {
        $user = $this->internal();
        $this->actingAs($user);

        Livewire::test(MyProfile::class)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('budi@okim.test', $user->fresh()->email);
    }

    public function test_email_must_be_unique(): void
    {
        $other = $this->internal('distribusi', ['email' => 'dipakai@okim.test']);
        $user = $this->internal();
        $this->actingAs($user);

        Livewire::test(MyProfile::class)
            ->fillForm(['email' => $other->email])
            ->call('save')
            ->assertHasFormErrors(['email']);
    }

    public function test_user_can_change_password_with_correct_current_password(): void
    {
        $user = $this->internal();
        $this->actingAs($user);

        Livewire::test(MyProfile::class)
            ->fillForm([
                'current_password' => 'SandiLama123',
                'password' => 'SandiBaru456',
                'password_confirmation' => 'SandiBaru456',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('SandiBaru456', $user->fresh()->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = $this->internal();
        $this->actingAs($user);

        Livewire::test(MyProfile::class)
            ->fillForm([
                'current_password' => 'SalahSekali1',
                'password' => 'SandiBaru456',
                'password_confirmation' => 'SandiBaru456',
            ])
            ->call('save')
            ->assertHasFormErrors(['current_password']);

        $this->assertTrue(Hash::check('SandiLama123', $user->fresh()->password));
    }

    public function test_weak_password_is_rejected(): void
    {
        $user = $this->internal();
        $this->actingAs($user);

        Livewire::test(MyProfile::class)
            ->fillForm([
                'current_password' => 'SandiLama123',
                'password' => 'pendek',
                'password_confirmation' => 'pendek',
            ])
            ->call('save')
            ->assertHasFormErrors(['password']);

        $this->assertTrue(Hash::check('SandiLama123', $user->fresh()->password));
    }

    public function test_password_confirmation_must_match(): void
    {
        $user = $this->internal();
        $this->actingAs($user);

        Livewire::test(MyProfile::class)
            ->fillForm([
                'current_password' => 'SandiLama123',
                'password' => 'SandiBaru456',
                'password_confirmation' => 'BedaSekali789',
            ])
            ->call('save')
            ->assertHasFormErrors(['password_confirmation']);
    }

    public function test_new_password_does_not_require_email_change(): void
    {
        $user = $this->internal();
        $this->actingAs($user);

        Livewire::test(MyProfile::class)
            ->fillForm([
                'current_password' => 'SandiLama123',
                'password' => 'SandiBaru456',
                'password_confirmation' => 'SandiBaru456',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('budi@okim.test', $user->fresh()->email);
    }

    public function test_pending_internal_cannot_access_page(): void
    {
        $this->assertFalse(
            MyProfile::canAccess()
        );

        $user = $this->internal(attributes: ['account_status' => AccountStatus::Pending]);
        $this->actingAs($user);

        $this->assertFalse(MyProfile::canAccess());

        $this->get('/admin/my-profile')->assertForbidden();
    }

    public function test_transportir_cannot_access_internal_profile(): void
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $user->assignRole('transportir');
        $this->actingAs($user);

        $this->assertFalse(MyProfile::canAccess());
    }

    public function test_page_is_not_registered_for_transportir_panel(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->map(fn ($route): string => $route->uri())
            ->filter(fn (string $uri): bool => str_starts_with($uri, 'transportir/my-profile'));

        $this->assertCount(0, $routes);
    }
}
