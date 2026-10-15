<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\User;
use App\Services\Registration\RegistrationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Tests\TestCase;

class EmailPasswordAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function validRegistration(): array
    {
        return [
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'phone' => '08123456789',
            'password' => 'Rahasia123',
            'password_confirmation' => 'Rahasia123',
        ];
    }

    private function mockGoogleUser(string $id, string $email): void
    {
        $googleUser = \Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn($id);
        $googleUser->shouldReceive('getEmail')->andReturn($email);
        $googleUser->shouldReceive('getName')->andReturn('Google User');
        $googleUser->shouldReceive('getNickname')->andReturn(null);

        Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
        Socialite::shouldReceive('user')->andReturn($googleUser);
    }

    public function test_register_page_is_accessible(): void
    {
        $this->get('/daftar')
            ->assertSuccessful()
            ->assertSee('Daftar sebagai transportir');
    }

    public function test_login_page_is_accessible(): void
    {
        $this->get('/masuk')
            ->assertSuccessful()
            ->assertSee('Masuk ke akun Anda');
    }

    public function test_legacy_login_route_redirects_to_public_login(): void
    {
        $this->get('/login')->assertRedirect(route('login.form'));
    }

    public function test_registration_creates_pending_transportir_account(): void
    {
        $this->post('/daftar', $this->validRegistration())
            ->assertRedirect(route('registration.profile.edit'));

        $user = User::where('email', 'budi@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame(AccountStatus::Pending, $user->account_status);
        $this->assertTrue($user->hasRole('transportir'));
        $this->assertTrue(Hash::check('Rahasia123', $user->password));
        $this->assertNull($user->google_id);
        $this->assertAuthenticatedAs($user);
    }

    public function test_registered_user_still_cannot_access_transportir_panel_until_approved(): void
    {
        $this->post('/daftar', $this->validRegistration());

        $user = User::where('email', 'budi@example.com')->first();

        $this->actingAs($user)->get('/transportir')->assertForbidden();
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'budi@example.com']);

        $this->post('/daftar', $this->validRegistration())
            ->assertSessionHasErrors('email');

        $this->assertSame(1, User::where('email', 'budi@example.com')->count());
    }

    public function test_registration_rejects_weak_password(): void
    {
        $payload = $this->validRegistration();
        $payload['password'] = 'lemah';
        $payload['password_confirmation'] = 'lemah';

        $this->post('/daftar', $payload)->assertSessionHasErrors('password');
    }

    public function test_registration_requires_password_confirmation(): void
    {
        $payload = $this->validRegistration();
        $payload['password_confirmation'] = 'Berbeda123';

        $this->post('/daftar', $payload)->assertSessionHasErrors('password');
    }

    public function test_registered_user_can_login_with_correct_password(): void
    {
        $this->post('/daftar', $this->validRegistration());
        auth()->logout();

        $this->post('/masuk', [
            'email' => 'budi@example.com',
            'password' => 'Rahasia123',
        ])->assertRedirect(route('registration.profile.edit'));

        $this->assertAuthenticated();
    }

    public function test_login_fails_with_wrong_password(): void
    {
        app(RegistrationService::class)->createPendingTransporter([
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'password' => 'Rahasia123',
        ]);

        $this->post('/masuk', [
            'email' => 'budi@example.com',
            'password' => 'salah-sekali',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_google_only_account_cannot_login_with_password(): void
    {
        User::factory()->create([
            'email' => 'googleonly@example.com',
            'password' => null,
            'google_id' => 'google-777',
        ]);

        $this->post('/masuk', [
            'email' => 'googleonly@example.com',
            'password' => 'apapun123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_email_registered_account_cannot_login_via_google(): void
    {
        $this->post('/daftar', $this->validRegistration());
        auth()->logout();

        $this->mockGoogleUser('google-999', 'budi@example.com');

        $this->get('/auth/google/callback')->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull(User::where('email', 'budi@example.com')->first()->google_id);
    }

    public function test_rejected_account_cannot_login_and_is_redirected_with_error(): void
    {
        $user = app(RegistrationService::class)->createPendingTransporter([
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'password' => 'Rahasia123',
        ]);
        $user->update(['account_status' => AccountStatus::Rejected]);

        $this->post('/masuk', [
            'email' => 'budi@example.com',
            'password' => 'Rahasia123',
        ])->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_suspended_account_cannot_login(): void
    {
        $user = app(RegistrationService::class)->createPendingTransporter([
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'password' => 'Rahasia123',
        ]);
        $user->update(['account_status' => AccountStatus::Suspended]);

        $this->post('/masuk', [
            'email' => 'budi@example.com',
            'password' => 'Rahasia123',
        ])->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_approved_transportir_login_redirects_to_panel(): void
    {
        $user = app(RegistrationService::class)->createPendingTransporter([
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'password' => 'Rahasia123',
        ]);
        $user->update(['account_status' => AccountStatus::Approved]);

        $this->post('/masuk', [
            'email' => 'budi@example.com',
            'password' => 'Rahasia123',
        ])->assertRedirect('/transportir');
    }

    public function test_authenticated_user_is_redirected_away_from_register_page(): void
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Pending]);
        $user->assignRole('transportir');

        $this->actingAs($user)->get('/daftar')->assertRedirect(route('registration.profile.edit'));
    }

    public function test_authenticated_approved_user_is_redirected_to_panel_from_register_page(): void
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $user->assignRole('transportir');

        $this->actingAs($user)->get('/daftar')->assertRedirect('/transportir');
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $user->assignRole('transportir');

        $this->actingAs($user)->post('/logout')->assertRedirect('/');

        $this->assertGuest();
    }
}
