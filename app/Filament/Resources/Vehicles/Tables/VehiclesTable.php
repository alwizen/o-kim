<?php

namespace App\Filament\Resources\Vehicles\Tables;

use App\Enums\DocumentVerificationStatus;
use App\Enums\VehicleStatus;
use App\Filament\Resources\Vehicles\VehicleResource;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VehiclesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('registration_number')
                    ->label('NOPOL')
                    ->searchable()
                    ->sortable()
                    ->url(fn ($record) => VehicleResource::getUrl('view', ['record' => $record])),
                TextColumn::make('transporter.company_name')
                    ->label('Perusahaan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('brand')
                    ->label('Merek')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('model')
                    ->label('Model')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('manufacture_year')
                    ->label('Tahun')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('tank_capacity')
                    ->label('Kapasitas (L)')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('chassis_number')
                    ->label('No. Rangka')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('engine_number')
                    ->label('No. Mesin')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('transport_type')
                    ->label('Jenis Angkutan')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('tank_material')
                    ->label('Bahan Tangki')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('documents_count')
                    ->label('Dokumen')
                    ->counts('documents'),
                TextColumn::make('pending_documents_count')
                    ->label('Perlu Verifikasi')
                    ->badge()
                    ->color('warning')
                    ->counts([
                        'documents as pending_documents_count' => fn ($query) => $query
                            ->whereNull('replaced_by_id')
                            ->where('verification_status', DocumentVerificationStatus::Pending->value),
                    ]),
                TextColumn::make('kimApplications_count')
                    ->label('Pengajuan')
                    ->counts('kimApplications'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (VehicleStatus $state) => $state->label())
                    ->color(fn (VehicleStatus $state) => $state === VehicleStatus::Active ? 'success' : 'gray'),
                TextColumn::make('transporter.status')
                    ->label('Status Perusahaan')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('transporter_id')
                    ->label('Perusahaan')
                    ->relationship('transporter', 'company_name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(VehicleStatus::cases())->mapWithKeys(
                        fn (VehicleStatus $s) => [$s->value => $s->label()]
                    )),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
