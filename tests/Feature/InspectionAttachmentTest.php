<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApplicationType;
use App\Enums\InspectionResultValue;
use App\Filament\Resources\KimApplications\Pages\InspectKimApplication;
use App\Filament\Resources\KimApplications\Pages\ViewKimApplication as AdminViewKimApplication;
use App\Filament\Transportir\Resources\KimApplications\Pages\ViewKimApplication as TransportirViewKimApplication;
use App\Models\InspectionItem;
use App\Models\InspectionResult;
use App\Models\KimApplication;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Application\KimApplicationService;
use App\Services\Inspection\InspectionAttachmentService;
use App\Services\Inspection\InspectionService;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InspectionChecklistSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class InspectionAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private Transporter $transporter;

    private User $transportir;

    private User $hsse;

    private User $qq;

    private Vehicle $vehicle;

    private KimApplication $application;

    private InspectionResult $result;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, DocumentTypeSeeder::class, InspectionChecklistSeeder::class]);
        Storage::fake('local');

        $this->transporter = Transporter::factory()->create();
        $this->transportir = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->transportir->assignRole('transportir');
        $this->transportir->transporters()->attach($this->transporter);
        $this->vehicle = Vehicle::factory()->create(['transporter_id' => $this->transporter->id]);

        $this->hsse = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->hsse->assignRole('hsse');

        $this->qq = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $this->qq->assignRole('qq');

        foreach (['STNK', 'KEUR', 'TERA'] as $code) {
            $this->uploadVehicleDocument($this->vehicle, $code);
        }

        $this->application = app(KimApplicationService::class)->createDraft($this->vehicle, ApplicationType::New);
        app(KimApplicationService::class)->submit($this->application, $this->transportir);

        $this->application->update([
            'inspection_scheduled_at' => now()->subDay(),
            'inspection_scheduled_by' => $this->hsse->id,
        ]);

        $this->result = app(InspectionService::class)->recordResult(
            $this->application->fresh(),
            $this->hsseItem(),
            InspectionResultValue::Compliant,
            $this->hsse,
        );
    }

    private function hsseItem(): InspectionItem
    {
        return InspectionItem::whereHas('checklist', fn ($q) => $q->where('department', 'hsse'))
            ->where('item_code', 'HSSE-01')->firstOrFail();
    }

    private function pdf(string $name = 'bukti.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 100, 'application/pdf');
    }

    private function image(string $name = 'foto.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 640, 480);
    }

    public function test_service_stores_multiple_files_in_one_upload(): void
    {
        $attachments = app(InspectionAttachmentService::class)->upload($this->result, $this->hsse, [
            $this->image('foto-1.jpg'),
            $this->image('foto-2.jpg'),
            $this->pdf('dokumen.pdf'),
        ]);

        $this->assertCount(3, $attachments);
        $this->assertSame(3, $this->result->attachments()->count());

        foreach ($attachments as $attachment) {
            Storage::disk('local')->assertExists($attachment->file_path);
            $this->assertSame($this->result->id, $attachment->inspection_result_id);
            $this->assertSame($this->hsse->id, $attachment->uploaded_by);
        }

        $this->assertDatabaseHas('inspection_result_attachments', [
            'inspection_result_id' => $this->result->id,
            'original_filename' => 'foto-1.jpg',
        ]);
    }

    public function test_upload_is_optional_and_empty_list_is_allowed(): void
    {
        $attachments = app(InspectionAttachmentService::class)->upload($this->result, $this->hsse, []);

        $this->assertSame([], $attachments);
        $this->assertSame(0, $this->result->attachments()->count());
    }

    public function test_disallowed_mime_type_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        app(InspectionAttachmentService::class)->upload($this->result, $this->hsse, [
            UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload'),
        ]);
    }

    public function test_file_larger_than_five_megabytes_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        app(InspectionAttachmentService::class)->upload($this->result, $this->hsse, [
            UploadedFile::fake()->create('besar.pdf', 5121, 'application/pdf'),
        ]);
    }

    public function test_officer_of_other_department_cannot_upload(): void
    {
        $this->expectException(ValidationException::class);

        app(InspectionAttachmentService::class)->upload($this->result, $this->qq, [
            $this->image(),
        ]);
    }

    public function test_officer_can_delete_attachment(): void
    {
        $attachment = app(InspectionAttachmentService::class)->upload($this->result, $this->hsse, [
            $this->image(),
        ])[0];

        app(InspectionAttachmentService::class)->delete($attachment, $this->hsse);

        $this->assertDatabaseMissing('inspection_result_attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertMissing($attachment->file_path);
    }

    public function test_officer_of_other_department_cannot_delete_attachment(): void
    {
        $attachment = app(InspectionAttachmentService::class)->upload($this->result, $this->hsse, [
            $this->image(),
        ])[0];

        $this->expectException(ValidationException::class);

        app(InspectionAttachmentService::class)->delete($attachment, $this->qq);
    }

    public function test_inspect_page_saves_multiple_attachments(): void
    {
        $items = InspectionItem::whereHas('checklist', fn ($q) => $q->where('department', 'hsse'))->get();
        $item = $this->hsseItem();

        $this->actingAs($this->hsse);

        $component = Livewire::test(InspectKimApplication::class, ['record' => $this->application->fresh()]);

        foreach ($items as $each) {
            $component->set("data.results.{$each->id}.result", InspectionResultValue::Compliant->value);
        }

        $component
            ->set("data.results.{$item->id}.attachments", [
                $this->image('foto-a.jpg'),
                $this->image('foto-b.jpg'),
            ])
            ->call('save')
            ->assertHasNoErrors();

        $result = $this->application->fresh()->inspectionResults()
            ->where('inspection_item_id', $item->id)
            ->firstOrFail();

        $this->assertSame(2, $result->attachments()->count());
    }

    public function test_attachment_is_visible_on_admin_view_page(): void
    {
        app(InspectionAttachmentService::class)->upload($this->result, $this->hsse, [
            $this->image('bukti-admin.jpg'),
        ]);

        $admin = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $admin->assignRole('super_admin');

        $this->actingAs($admin);

        Livewire::test(AdminViewKimApplication::class, [
            'record' => $this->application->getRouteKey(),
        ])
            ->assertSuccessful()
            ->assertSee('bukti-admin.jpg');
    }

    public function test_attachment_is_visible_on_transportir_view_page(): void
    {
        app(InspectionAttachmentService::class)->upload($this->result, $this->hsse, [
            $this->image('bukti-transportir.jpg'),
        ]);

        Filament::setCurrentPanel('transportir');

        $this->actingAs($this->transportir);

        Livewire::test(TransportirViewKimApplication::class, [
            'record' => $this->application->getRouteKey(),
        ])
            ->assertSuccessful()
            ->assertSee('bukti-transportir.jpg');
    }

    public function test_attachment_preview_and_download_are_authorized_for_owner(): void
    {
        $attachment = app(InspectionAttachmentService::class)->upload($this->result, $this->hsse, [
            $this->pdf('bukti.pdf'),
        ])[0];

        $this->actingAs($this->transportir)
            ->get(route('inspection-attachments.preview', $attachment))
            ->assertSuccessful();

        $this->actingAs($this->transportir)
            ->get(route('inspection-attachments.download', $attachment))
            ->assertSuccessful();
    }

    public function test_attachment_is_forbidden_for_other_transporter(): void
    {
        $attachment = app(InspectionAttachmentService::class)->upload($this->result, $this->hsse, [
            $this->pdf('bukti.pdf'),
        ])[0];

        $otherTransporter = Transporter::factory()->create();
        $otherUser = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $otherUser->assignRole('transportir');
        $otherUser->transporters()->attach($otherTransporter);

        $this->actingAs($otherUser)
            ->get(route('inspection-attachments.preview', $attachment))
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->get(route('inspection-attachments.download', $attachment))
            ->assertForbidden();
    }

    public function test_attachment_preview_requires_authentication(): void
    {
        $attachment = app(InspectionAttachmentService::class)->upload($this->result, $this->hsse, [
            $this->pdf('bukti.pdf'),
        ])[0];

        $this->get(route('inspection-attachments.preview', $attachment))
            ->assertRedirect(route('login'));
    }

    public function test_internal_officer_can_preview_any_attachment(): void
    {
        $attachment = app(InspectionAttachmentService::class)->upload($this->result, $this->hsse, [
            $this->pdf('bukti.pdf'),
        ])[0];

        $admin = User::factory()->create(['account_status' => AccountStatus::Approved]);
        $admin->assignRole('super_admin');

        $this->actingAs($admin)
            ->get(route('inspection-attachments.preview', $attachment))
            ->assertSuccessful();
    }

    public function test_attachment_relations_are_exposed_on_result(): void
    {
        $attachment = app(InspectionAttachmentService::class)->upload($this->result, $this->hsse, [
            $this->image('relasi.jpg'),
        ])[0];

        $this->assertTrue($this->result->fresh()->attachments->contains($attachment));
        $this->assertSame($this->result->id, $attachment->result->id);
        $this->assertSame($this->hsse->id, $attachment->uploader->id);
    }
}
