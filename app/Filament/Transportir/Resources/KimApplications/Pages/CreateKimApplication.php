<?php

namespace App\Filament\Transportir\Resources\KimApplications\Pages;

use App\Enums\ApplicationType;
use App\Filament\Transportir\Resources\KimApplications\KimApplicationResource;
use App\Models\Vehicle;
use App\Services\Application\KimApplicationService;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateKimApplication extends CreateRecord
{
    protected static string $resource = KimApplicationResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data Pengajuan')
                ->description('Hanya kendaraan dengan seluruh dokumen wajib terverifikasi yang dapat dipilih.')
                ->columns(2)
                ->schema([
                    Select::make('vehicle_id')
                        ->label('Kendaraan')
                        ->options(fn (): array => $this->availableVehicles())
                        ->required()
                        ->searchable()
                        ->native(false)
                        ->helperText(fn (): ?string => $this->availableVehicles() === []
                            ? 'Belum ada kendaraan yang siap diajukan. Pastikan seluruh dokumen wajib sudah diunggah dan diverifikasi petugas.'
                            : null),
                    Select::make('application_type')
                        ->label('Jenis Pengajuan')
                        ->options(collect(ApplicationType::cases())->mapWithKeys(
                            fn (ApplicationType $t) => [$t->value => $t->label()]
                        ))
                        ->required()
                        ->native(false),
                ]),
        ]);
    }

    /**
     * Daftar kendaraan milik transportir yang siap diajukan (NOPOL => id).
     *
     * @return array<int, string>
     */
    private function availableVehicles(): array
    {
        $transporterIds = auth()->user()->transporters()->pluck('transporters.id')->all();

        if ($transporterIds === []) {
            return [];
        }

        return app(KimApplicationService::class)
            ->readyVehiclesQuery($transporterIds)
            ->orderBy('registration_number')
            ->pluck('registration_number', 'id')
            ->all();
    }

    protected function handleRecordCreation(array $data): Model
    {
        $vehicle = Vehicle::findOrFail($data['vehicle_id']);

        $owns = auth()->user()->transporters()
            ->where('transporters.id', $vehicle->transporter_id)
            ->exists();

        if (! $owns) {
            throw ValidationException::withMessages([
                'vehicle_id' => 'Kendaraan tidak ditemukan.',
            ]);
        }

        // Validasi ulang di backend: dokumen wajib harus sudah terverifikasi.
        $service = app(KimApplicationService::class);
        $missing = $service->missingRequiredDocumentCodes($vehicle);

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'vehicle_id' => 'Kendaraan belum siap diajukan. Dokumen wajib belum diverifikasi: '.$missing->implode(', ').'.',
            ]);
        }

        return $service->createDraft(
            $vehicle,
            ApplicationType::from($data['application_type'])
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
