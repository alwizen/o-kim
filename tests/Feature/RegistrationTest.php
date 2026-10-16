<?php

namespace Tests\Feature;

use App\Actions\Registration\ApproveTransporterRegistration;
use App\Actions\Registration\RejectTransporterRegistration;
use App\Enums\AccountStatus;
use App\Enums\TransporterStatus;
use App\Models\Transporter;
use App\Models\User;
use App\Notifications\TransporterApproved;
use App\Notifications\TransporterRegistered;
use App\Notifications\TransporterRejected;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function mockGoogleUser(string $id = 'google-123', string $email = 'pic@example.com'): void
    {
        $googleUser = \Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn($id);
        $googleUser->shouldReceive('getEmail')->andReturn($email);
        $googleUser->shouldReceive('getName')->andReturn('PIC Perusahaan');
        $googleUser->shouldReceive('getNickname')->andReturn(null);

        Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
        Socialite::shouldReceive('user')->andReturn($googleUser);
        Socialite::shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com'));
    }

    public function test_landing_page_is_publicly_accessible(): void
    {
        $this->get('/')->assertSuccessful();
    }

    public function test_google_redirect_uses_rate_limited_route(): void
    {
        $this->mockGoogleUser();
        $response = $this->get('/auth/google');
        $response->assertRedirect();
    }

    public function test_new_google_user_is_created_with_pending_status(): void
    {
        $this->mockGoogleUser();

        $this->get('/auth/google/callback');

        $user = User::where('email', 'pic@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame(AccountStatus::Pending, $user->account_status);
        $this->assertSame('google-123', $user->google_id);
        $this->assertTrue($user->hasRole('transportir'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_google_callback_does_not_merge_accounts_by_email(): void
    {
        User::factory()->create([
            'email' => 'pic@example.com',
            'google_id' => null,
        ]);

        $this->mockGoogleUser(id: 'google-999', email: 'pic@example.com');

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/');
        $this->assertGuest();
        $this->assertNull(User::where('email', 'pic@example.com')->first()->google_id);
    }

    public function test_pending_user_can_view_registration_profile_form(): void
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Pending]);
        $user->assignRole('transportir');

        $this->actingAs($user)
            ->get(route('registration.profile.edit'))
            ->assertSuccessful()
            ->assertSee('Profil Perusahaan Transportir')
            ->assertSee('Nama Perusahaan');
    }

    public function test_pending_user_can_fill_company_profile(): void
    {
        Notification::fake();

        $user = User::factory()->create(['account_status' => AccountStatus::Pending]);
        $user->assignRole('transportir');

        $response = $this->actingAs($user)->post(route('registration.profile.update'), [
            'pic_name' => 'Budi Santoso',
            'company_name' => 'PT Angkutan Tegal',
            'company_address' => 'Jl. Raya Tegal No. 1',
            'phone' => '0283-123456',
            'legal_identifier' => 'NIB-123',
        ]);

        $response->assertRedirect(route('registration.profile.edit'));

        $transporter = Transporter::where('company_name', 'PT Angkutan Tegal')->first();
        $this->assertNotNull($transporter);
        $this->assertTrue($user->fresh()->transporters->contains($transporter));
        $this->assertSame('Budi Santoso', $user->fresh()->name);

        Notification::assertSentTo($user, TransporterRegistered::class);
    }

    public function test_profile_requires_mandatory_fields(): void
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Pending]);
        $user->assignRole('transportir');

        $this->actingAs($user)
            ->post(route('registration.profile.update'), [])
            ->assertSessionHasErrors(['pic_name', 'company_name', 'company_address', 'phone']);
    }

    public function test_approved_user_cannot_edit_registration_profile(): void
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $user->assignRole('transportir');

        $this->actingAs($user)
            ->get(route('registration.profile.edit'))
            ->assertRedirect('/');
    }

    public function test_admin_can_approve_pending_registration(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $admin->assignRole('admin_verifikasi');

        $target = User::factory()->create(['account_status' => AccountStatus::Pending]);
        $target->assignRole('transportir');
        $transporter = Transporter::create([
            'company_name' => 'PT Uji',
            'company_address' => 'Alamat',
            'phone' => '081234',
        ]);
        $target->transporters()->attach($transporter);

        app(ApproveTransporterRegistration::class)->execute($target, $admin);

        $target->refresh();
        $this->assertSame(AccountStatus::Approved, $target->account_status);
        $this->assertSame($admin->id, $target->reviewed_by);
        $this->assertNotNull($target->reviewed_at);
        $this->assertSame(TransporterStatus::Approved, $transporter->fresh()->status);

        Notification::assertSentTo($target, TransporterApproved::class);
    }

    public function test_admin_can_reject_pending_registration_with_reason(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $admin->assignRole('admin_verifikasi');

        $target = User::factory()->create(['account_status' => AccountStatus::Pending]);
        $target->assignRole('transportir');

        app(RejectTransporterRegistration::class)->execute($target, $admin, 'Dokumen tidak lengkap');

        $target->refresh();
        $this->assertSame(AccountStatus::Rejected, $target->account_status);
        $this->assertSame('Dokumen tidak lengkap', $target->rejection_reason);

        Notification::assertSentTo($target, TransporterRejected::class);
    }

    public function test_rejection_requires_reason(): void
    {
        $admin = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $target = User::factory()->create(['account_status' => AccountStatus::Pending]);

        $this->expectException(ValidationException::class);

        app(RejectTransporterRegistration::class)->execute($target, $admin, '');
    }

    public function test_cannot_approve_non_pending_account(): void
    {
        $admin = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $target = User::factory()->create(['account_status' => AccountStatus::Approved]);

        $this->expectException(ValidationException::class);

        app(ApproveTransporterRegistration::class)->execute($target, $admin);
    }

    public function test_pending_user_cannot_access_transportir_panel(): void
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Pending]);
        $user->assignRole('transportir');

        $this->actingAs($user)->get('/transportir')->assertForbidden();
    }

    public function test_approved_transportir_can_access_transportir_panel(): void
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $user->assignRole('transportir');

        $this->actingAs($user)->get('/transportir')->assertSuccessful();
    }

    public function test_admin_verification_page_requires_permission(): void
    {
        $transportir = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $transportir->assignRole('transportir');

        $this->actingAs($transportir)->get('/admin')->assertForbidden();
    }

    public function test_internal_user_is_not_registered_via_public_oauth(): void
    {
        $this->mockGoogleUser(id: 'new-google', email: 'new@example.com');

        $this->get('/auth/google/callback');

        $user = User::where('email', 'new@example.com')->first();
        $this->assertTrue($user->hasRole('transportir'));
        $this->assertFalse($user->can('access_admin_panel'));
    }
}
