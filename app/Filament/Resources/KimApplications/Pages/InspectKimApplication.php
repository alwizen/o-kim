<?php

namespace App\Filament\Resources\KimApplications\Pages;

use App\Enums\Department;
use App\Enums\InspectionResultValue;
use App\Filament\Resources\KimApplications\KimApplicationResource;
use App\Models\InspectionChecklist;
use App\Models\InspectionItem;
use App\Models\KimApplication;
use App\Services\Inspection\InspectionService;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class InspectKimApplication extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = KimApplicationResource::class;

    protected string $view = 'filament.resources.kim-applications.pages.inspect-kim-application';

    public KimApplication $record;

    public ?array $data = [];

    public function mount(KimApplication $record): void
    {
        $this->record = $record;
        $this->form->fill();
    }

    public function getTitle(): string
    {
        return 'Pemeriksaan: '.$this->record->application_number;
    }

    public function form(Schema $schema): Schema
    {
        return $this->buildInspectionForm($schema->statePath('data'));
    }

    private function buildInspectionForm(Schema $schema): Schema
    {
        $user = auth()->user();
        $sections = [];

        foreach (Department::inspectingDepartments() as $department) {
            if (! $user->can($department->inspectPermission())) {
                continue;
            }

            $checklist = InspectionChecklist::where('department', $department->value)
                ->where('is_active', true)
                ->with(['items' => fn ($query) => $query->where('is_active', true), 'items.points' => fn ($query) => $query->where('is_active', true)])
                ->first();

            if (! $checklist || $checklist->items->isEmpty()) {
                continue;
            }

            $fields = [];
            foreach ($checklist->items as $item) {
                $existing = $this->record->inspectionResults()
                    ->where('inspection_item_id', $item->id)
                    ->first();

                $fields[] = Radio::make("results.{$item->id}.result")
                    ->label($item->item_code.' — '.$item->label)
                    ->options([
                        InspectionResultValue::Compliant->value => 'Sesuai',
                        InspectionResultValue::Finding->value => 'Temuan',
                        InspectionResultValue::NotApplicable->value => 'Tidak Berlaku',
                    ])
                    ->default($existing?->result?->value)
                    ->required()
                    ->live();

                if ($item->points->isNotEmpty()) {
                    $checkedPoints = array_keys(array_filter($existing?->checkedPointMap() ?? []));

                    $fields[] = CheckboxList::make("results.{$item->id}.points")
                        ->label('Sub-butir pemeriksaan')
                        ->options($item->points->pluck('label', 'id'))
                        ->default($checkedPoints)
                        ->columns(2)
                        ->bulkToggleable()
                        ->helperText('Centang sub-butir yang sesuai kondisi kendaraan.');
                }

                $fields[] = Textarea::make("results.{$item->id}.finding_description")
                    ->label('Deskripsi Temuan')
                    ->rows(2)
                    ->visible(fn ($get) => $get("results.{$item->id}.result") === InspectionResultValue::Finding->value)
                    ->required(fn ($get) => $get("results.{$item->id}.result") === InspectionResultValue::Finding->value)
                    ->default($existing?->finding?->description);

                $fields[] = Textarea::make("results.{$item->id}.notes")
                    ->label('Catatan (opsional)')
                    ->rows(1)
                    ->default($existing?->notes);
            }

            $sections[] = Section::make('Checklist '.$department->label())
                ->description(new HtmlString(
                    'Pengajuan '.$this->record->application_number.' — NOPOL <strong>'.
                    e($this->record->vehicle->registration_number).'</strong>'
                ))
                ->schema($fields)
                ->collapsible();
        }

        if (empty($sections)) {
            $sections[] = Section::make('Tidak Ada Akses')
                ->schema([
                    TextEntry::make('no_access')
                        ->label('')
                        ->state('Anda tidak memiliki wewenang pemeriksaan untuk fungsi mana pun.'),
                ]);
        }

        return $schema->components($sections);
    }

    public function save(InspectionService $service): void
    {
        $state = $this->form->getState();
        $results = $state['results'] ?? [];

        $saved = 0;
        $errors = [];

        foreach ($results as $itemId => $data) {
            if (empty($data['result'])) {
                continue;
            }

            $item = InspectionItem::find($itemId);
            if (! $item) {
                continue;
            }

            try {
                $service->recordResult(
                    $this->record->fresh(),
                    $item,
                    InspectionResultValue::from($data['result']),
                    auth()->user(),
                    notes: $data['notes'] ?? null,
                    findingDescription: $data['finding_description'] ?? null,
                    pointIds: $data['points'] ?? [],
                );
                $saved++;
            } catch (ValidationException $e) {
                $errors[] = collect($e->errors())->flatten()->first();
            }
        }

        if ($errors) {
            Notification::make()
                ->title('Sebagian hasil gagal disimpan')
                ->body(implode(' ', $errors))
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title($saved > 0 ? "{$saved} hasil pemeriksaan disimpan" : 'Tidak ada perubahan')
            ->success()
            ->send();

        $this->redirect(KimApplicationResource::getUrl('view', ['record' => $this->record]));
    }
}
