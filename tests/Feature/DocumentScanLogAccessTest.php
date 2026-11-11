<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\DocumentScanType;
use App\Filament\Resources\DocumentScanLogs\DocumentScanLogResource;
use App\Models\DocumentScanLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentScanLogAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $user->assignRole($role);

        return $user;
    }

    public function test_super_admin_with_audit_permission_can_access_log(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));

        $this->assertTrue(DocumentScanLogResource::canAccess());
    }

    public function test_roles_without_audit_permission_cannot_access_log(): void
    {
        foreach (['ftm', 'pengawas_hsse', 'admin_verifikasi', 'hsse'] as $role) {
            $this->actingAs($this->userWithRole($role));

            $this->assertFalse(
                DocumentScanLogResource::canAccess(),
                "Role [{$role}] tidak boleh melihat Log Verifikasi QR.",
            );

            auth()->logout();
        }
    }

    public function test_log_helpers_resolve_document_and_signer(): void
    {
        $baLog = new DocumentScanLog(['document_type' => DocumentScanType::BeritaAcara]);
        $kimLog = new DocumentScanLog(['document_type' => DocumentScanType::Kim]);

        $this->assertSame('Berita Acara', $baLog->document_type->label());
        $this->assertSame('Kartu KIM', $kimLog->document_type->label());
        $this->assertSame('—', $baLog->documentNumber());
        $this->assertSame('—', $kimLog->registrationNumber());
    }
}
