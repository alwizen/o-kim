<?php

namespace App\Filament\Transportir\Resources\KimApplications\Pages;

use App\Enums\ApplicationType;
use App\Filament\Transportir\Resources\KimApplications\KimApplicationResource;
use App\Models\Vehicle;
use App\Services\Application\KimApplicationService;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateKimApplication extends CreateRecord
{
    protected static string $resource = KimApplicationResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('vehicle_id')
                ->label('Kendaraan')
                ->options(
                    Vehicle::whereIn(
                        'transporter_id',
                        auth()->user()->transporters()->pluck('transporters.id')
                    )->pluck('registration_number', 'id')
                )
                ->required()
                ->searchable(),
            Select::make('application_type')
                ->label('Jenis Pengajuan')
                ->options(collect(ApplicationType::cases())->mapWithKeys(
                    fn (ApplicationType $t) => [$t->value => $t->label()]
                ))
                ->required(),
        ]);
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

        return app(KimApplicationService::class)->createDraft(
            $vehicle,
            ApplicationType::from($data['application_type'])
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
