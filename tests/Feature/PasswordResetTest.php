<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function transportir(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'account_status' => AccountStatus::Approved,
            'email' => 'transportir@example.com',
            'password' => 'Rahasia123',
        ], $attributes));
        $user->assignRole('transportir');

        return $user;
    }

    private function internalUser(): User
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::Approved,
            'email' => 'petugas@okim.test',
            'password' => 'Rahasia123',
        ]);
        $user->assignRole('hsse');

        return $user;
    }

    private function googleUser(): User
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::Approved,
            'email' => 'google@example.com',
            'google_id' => 'google-123',
            'password' => null,
        ]);
        $user->assignRole('transportir');

        return $user;
    }

    // ---------- Halaman ----------

    public function test_forgot_password_page_is_accessible(): void
    {
        $this->get(route('password.request'))
            ->assertSuccessful()
            ->assertSee('Kirim Tautan Reset');
    }

    public function test_reset_password_page_is_accessible(): void
    {
        $this->get(route('password.reset', ['token' => 'dummy-token', 'email' => 'transportir@example.com']))
            ->assertSuccessful()
            ->assertSee('Atur ulang kata sandi')
            ->assertSee('transportir@example.com');
    }

    public function test_login_page_links_to_forgot_password(): void
    {
        $this->get('/masuk')
            ->assertSuccessful()
            ->assertSee(route('password.request'));
    }

    public function test_admin_login_page_links_to_forgot_password(): void
    {
        $this->get('/admin/login')
            ->assertSuccessful()
            ->assertSee(route('password.request'));
    }

    // ---------- Permintaan tautan ----------

    public function test_transportir_receives_reset_link(): void
    {
        Notification::fake();
        $user = $this->transportir();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_internal_user_receives_reset_link(): void
    {
        Notification::fake();
        $user = $this->internalUser();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_google_only_account_cannot_request_reset_link(): void
    {
        Notification::fake();
        $user = $this->googleUser();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status')
            ->assertSessionMissing('error');

        Notification::assertNothingSent();
    }

    public function test_unknown_email_does_not_reveal_account_existence(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'tidak-ada@example.com'])
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();
    }

    public function test_reset_request_response_is_identical_for_google_unknown_and_password_accounts(): void
    {
        Notification::fake();

        $passwordAccount = $this->transportir();
        $googleAccount = $this->googleUser();

        $expectedStatus = 'Jika email tersebut terdaftar dan menggunakan kata sandi, tautan reset telah kami kirim. '
            .'Periksa kotak masuk dan folder spam.';

        foreach ([$passwordAccount->email, $googleAccount->email, 'tidak-ada@example.com'] as $email) {
            $this->post(route('password.email'), ['email' => $email])
                ->assertSessionHas('status', $expectedStatus)
                ->assertSessionMissing('error');
        }
    }

    public function test_forgot_password_requires_valid_email(): void
    {
        $this->post(route('password.email'), ['email' => 'bukan-email'])
            ->assertSessionHasErrors('email');
    }

    // ---------- Reset kata sandi ----------

    public function test_transportir_can_reset_password_and_is_redirected_to_public_login(): void
    {
        $user = $this->transportir();
        $token = Password::createToken($user);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'SandiBaru123',
            'password_confirmation' => 'SandiBaru123',
        ])->assertRedirect('/masuk')
            ->assertSessionHas('status');

        $this->assertTrue(Hash::check('SandiBaru123', $user->fresh()->password));
    }

    public function test_internal_user_can_reset_password_and_is_redirected_to_admin_login(): void
    {
        $user = $this->internalUser();
        $token = Password::createToken($user);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'SandiBaru123',
            'password_confirmation' => 'SandiBaru123',
        ])->assertRedirect('/admin/login');

        $this->assertTrue(Hash::check('SandiBaru123', $user->fresh()->password));
    }

    public function test_invalid_token_is_rejected(): void
    {
        $user = $this->transportir();

        $this->post(route('password.store'), [
            'token' => 'token-palsu',
            'email' => $user->email,
            'password' => 'SandiBaru123',
            'password_confirmation' => 'SandiBaru123',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('Rahasia123', $user->fresh()->password));
    }

    public function test_reset_requires_strong_password(): void
    {
        $user = $this->transportir();
        $token = Password::createToken($user);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'lemah',
            'password_confirmation' => 'lemah',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('Rahasia123', $user->fresh()->password));
    }

    public function test_new_password_can_be_used_to_log_in(): void
    {
        $user = $this->transportir();
        $token = Password::createToken($user);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'SandiBaru123',
            'password_confirmation' => 'SandiBaru123',
        ]);

        auth()->logout();

        $this->post('/masuk', [
            'email' => $user->email,
            'password' => 'SandiBaru123',
        ])->assertRedirect('/transportir');

        $this->assertAuthenticatedAs($user);
    }
}
