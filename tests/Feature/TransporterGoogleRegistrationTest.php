<?php

namespace Tests\Feature;

use App\Actions\Registration\ApproveTransporterRegistration;
use App\Enums\AccountStatus;
use App\Filament\Resources\Transporters\TransporterResource;
use App\Models\Transporter;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

/**
 * Transportir yang mendaftar lewat Google wajib mengisi profil perusahaan lebih dulu.
 * Baru setelah submit, datanya masuk antrean Verifikasi Transportir.
 */
class TransporterGoogleRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function fakeGoogleUser(string $id = 'google-123', string $email = 'pt.contoh@okim.test'): void
    {
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn($id);
        $socialiteUser->shouldReceive('getEmail')->andReturn($email);
        $socialiteUser->shouldReceive('getName')->andReturn('Budi Santoso');
        $socialiteUser->shouldReceive('getNickname')->andReturn('budi');

        Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);
    }

    private function registerViaGoogle(): User
    {
        $this->fakeGoogleUser();

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('registration.profile.edit'));

        return User::where('email', 'pt.contoh@okim.test')->firstOrFail();
    }

    private function validProfilePayload(array $overrides = []): array
    {
        return array_merge([
            'pic_name' => 'Budi Santoso',
            'company_name' => 'PT Contoh Trans',
            'company_address' => 'Jl. Contoh No. 1 Tegal',
            'phone' => '081234567890',
            'legal_identifier' => '1234567890',
        ], $overrides);
    }

    public function test_google_registration_creates_pending_transporter_without_profile(): void
    {
        $user = $this->registerViaGoogle();

        $this->assertSame(AccountStatus::Pending, $user->account_status);
        $this->assertTrue($user->hasRole('transportir'));
        $this->assertNull($user->transporters()->first());
    }

    public function test_transporter_without_profile_is_hidden_from_verification_list(): void
    {
        $user = $this->registerViaGoogle();

        $ids = TransporterResource::getEloquentQuery()->pluck('id');

        $this->assertFalse($ids->contains($user->id));
    }

    public function test_transporter_without_profile_cannot_be_approved(): void
    {
        $reviewer = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $reviewer->assignRole('admin_verifikasi');

        $target = $this->registerViaGoogle();

        $this->expectException(ValidationException::class);

        app(ApproveTransporterRegistration::class)->execute($target, $reviewer);
    }

    public function test_submitting_profile_redirects_to_waiting_page(): void
    {
        $user = $this->registerViaGoogle();

        $this->actingAs($user)
            ->post(route('registration.profile.update'), $this->validProfilePayload())
            ->assertRedirect(route('registration.pending.transporter'));

        $transporter = $user->fresh()->transporters()->first();

        $this->assertNotNull($transporter);
        $this->assertSame('PT Contoh Trans', $transporter->company_name);
        $this->assertNotNull($transporter->profile_submitted_at);
    }

    public function test_transporter_appears_in_verification_list_after_submitting(): void
    {
        $user = $this->registerViaGoogle();

        $this->actingAs($user)->post(route('registration.profile.update'), $this->validProfilePayload());

        $ids = TransporterResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($ids->contains($user->id));
    }

    public function test_profile_form_is_locked_after_submission(): void
    {
        $user = $this->registerViaGoogle();

        $this->actingAs($user)->post(route('registration.profile.update'), $this->validProfilePayload());

        $this->actingAs($user)
            ->get(route('registration.profile.edit'))
            ->assertRedirect(route('registration.pending.transporter'));
    }

    public function test_profile_cannot_be_resubmitted_after_submission(): void
    {
        $user = $this->registerViaGoogle();

        $this->actingAs($user)->post(route('registration.profile.update'), $this->validProfilePayload());

        $this->actingAs($user)
            ->post(route('registration.profile.update'), $this->validProfilePayload(['company_name' => 'PT Diubah']))
            ->assertRedirect(route('registration.pending.transporter'));

        $this->assertSame(
            'PT Contoh Trans',
            $user->fresh()->transporters()->first()->company_name,
        );
    }

    public function test_waiting_page_is_accessible_and_shows_company_data(): void
    {
        $user = $this->registerViaGoogle();
        $this->actingAs($user)->post(route('registration.profile.update'), $this->validProfilePayload());

        $this->actingAs($user)
            ->get(route('registration.pending.transporter'))
            ->assertSuccessful()
            ->assertSee('PT Contoh Trans')
            ->assertSee('Budi Santoso')
            ->assertSee('Jl. Contoh No. 1 Tegal')
            ->assertSee('Menunggu Verifikasi');
    }

    public function test_waiting_page_requires_transporter_role(): void
    {
        $internal = User::factory()->create(['account_status' => AccountStatus::Pending]);
        $internal->assignRole('hsse');

        $this->actingAs($internal)
            ->get(route('registration.pending.transporter'))
            ->assertForbidden();
    }

    public function test_submitted_transporter_is_routed_to_waiting_page_by_home_url(): void
    {
        $user = $this->registerViaGoogle();
        $this->actingAs($user)->post(route('registration.profile.update'), $this->validProfilePayload());

        $this->assertSame(route('registration.pending.transporter'), $user->fresh()->homeUrl());
    }

    public function test_unsubmitted_transporter_is_routed_to_profile_form_by_home_url(): void
    {
        $this->assertSame(
            route('registration.profile.edit'),
            $this->registerViaGoogle()->homeUrl(),
        );
    }

    public function test_submitted_transporter_cannot_reach_operational_routes(): void
    {
        $otherTransporter = Transporter::create([
            'company_name' => 'PT Lain',
            'company_address' => 'Jl. Lain',
            'phone' => '0812',
        ]);

        $user = $this->registerViaGoogle();
        $this->actingAs($user)->post(route('registration.profile.update'), $this->validProfilePayload());

        $application = \App\Models\KimApplication::factory()->create(['transporter_id' => $otherTransporter->id]);

        $this->actingAs($user->fresh())
            ->get(route('inspection-report.download', $application))
            ->assertForbidden();
    }
}
